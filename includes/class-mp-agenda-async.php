<?php
/**
 * Exécute la synchro Google Agenda et les notifications (email) APRÈS l'envoi de
 * la réponse HTTP au client, pour que les actions de RDV (création, modification,
 * suppression, réservation publique) répondent instantanément.
 *
 * Fonctionnement : les méthodes de MP_Agenda_REST_API qui déclenchaient auparavant
 * push_appointment()/delete_event()/send_appointment_notifications() de façon
 * synchrone appellent désormais MP_Agenda_Async::queue() puis retournent tout de
 * suite leur réponse. Le traitement réel a lieu au hook 'shutdown' (déclenché de
 * façon fiable par WordPress à la fin de CHAQUE requête PHP, y compris après le
 * wp_die()/exit() utilisé par wp_send_json_success()/wp_send_json_error() côté
 * transport admin-ajax.php) :
 *
 * - Si fastcgi_finish_request() est disponible (PHP-FPM, la configuration la plus
 *   courante des hébergements mutualisés modernes dont OVH) : on clôt IMMÉDIATEMENT
 *   la connexion avec le client (la réponse JSON, déjà générée à ce stade, lui est
 *   envoyée), puis on continue à exécuter le script pour faire la synchro Google et
 *   envoyer les emails. Le client ne voit plus du tout ce temps de traitement.
 * - Sinon (ex. mod_php Apache), rien ne garantit qu'exécuter le travail ici même
 *   libère le client plus tôt qu'avant : on reprogramme donc chaque tâche comme un
 *   événement wp-cron "immédiat" (exécuté dans une requête HTTP interne séparée,
 *   non bloquante pour le client courant) et on force son déclenchement sans
 *   attendre la prochaine visite du site.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Async.
 */
class MP_Agenda_Async {

	/**
	 * Nom du hook wp-cron utilisé comme repli lorsque fastcgi_finish_request()
	 * n'est pas disponible.
	 *
	 * @var string
	 */
	const HOOK = 'mp_agenda_async_tasks';

	/**
	 * Tâches mises en file pour la requête HTTP en cours, traitées au hook 'shutdown'.
	 *
	 * @var array
	 */
	private static $queue = array();

	/**
	 * Enregistre les hooks nécessaires (à appeler une fois, depuis MP_Agenda::run()).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'shutdown', array( __CLASS__, 'dispatch' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_task' ), 10, 2 );
	}

	/**
	 * Met en file une tâche (synchro Google + notifications) pour un rendez-vous,
	 * à exécuter juste après l'envoi de la réponse HTTP au client.
	 *
	 * @param string $action      'create', 'update', 'delete' ou 'cancel'.
	 * @param array  $appointment Instantané complet du rendez-vous au moment de
	 *                            l'appel. On passe toujours les données déjà en
	 *                            main (plutôt qu'un simple ID à rerequêter plus
	 *                            tard) car pour 'delete' la ligne n'existe déjà
	 *                            plus en base au moment de l'exécution différée.
	 * @return void
	 */
	public static function queue( $action, $appointment ) {
		if ( empty( $appointment ) ) {
			return;
		}
		self::$queue[] = array( $action, $appointment );
	}

	/**
	 * Hook 'shutdown' : point unique déclenché à la fin de CHAQUE requête PHP
	 * (admin-ajax.php et /wp-json/ compris), y compris après un wp_die()/exit().
	 * C'est donc l'endroit fiable pour agir "après l'envoi de la réponse" quel
	 * que soit le transport utilisé par l'appelant.
	 *
	 * @return void
	 */
	public static function dispatch() {
		if ( empty( self::$queue ) ) {
			return;
		}

		$jobs        = self::$queue;
		self::$queue = array();

		// Évite qu'une déconnexion du client (normale ici : il a déjà sa réponse,
		// ou est en train de la recevoir) n'interrompe le traitement en arrière-plan.
		ignore_user_abort( true );

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			self::run_jobs( $jobs );
			return;
		}

		foreach ( $jobs as $job ) {
			wp_schedule_single_event( time(), self::HOOK, $job );
		}

		// Déclenche immédiatement le passage de wp-cron (requête interne non
		// bloquante) au lieu d'attendre la prochaine visite du site.
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Callback du hook wp-cron mp_agenda_async_tasks (repli sans fastcgi_finish_request).
	 *
	 * @param string $action      Voir queue().
	 * @param array  $appointment Voir queue().
	 * @return void
	 */
	public static function run_task( $action, $appointment ) {
		self::run_jobs( array( array( $action, $appointment ) ) );
	}

	/**
	 * Exécute effectivement la synchro Google Agenda + les notifications email
	 * pour une liste de tâches.
	 *
	 * @param array $jobs Liste de array( $action, $appointment ).
	 * @return void
	 */
	private static function run_jobs( $jobs ) {
		if ( empty( $jobs ) ) {
			return;
		}

		$google_sync   = new MP_Agenda_Google_Sync();
		$notifications = new MP_Agenda_Notifications();

		foreach ( $jobs as $job ) {
			list( $action, $appointment ) = $job;

			if ( empty( $appointment ) ) {
				continue;
			}

			try {
				switch ( $action ) {
					case 'create':
						$google_sync->push_appointment( $appointment );
						$notifications->send_appointment_notifications( $appointment );
						break;

					case 'update':
						$google_sync->push_appointment( $appointment );
						$notifications->send_appointment_change_notifications( $appointment, 'modified' );
						break;

					case 'delete':
						if ( ! empty( $appointment['google_event_id'] ) ) {
							$google_sync->delete_event( $appointment );
						}
						break;

					case 'cancel':
						if ( ! empty( $appointment['google_event_id'] ) ) {
							$google_sync->delete_event( $appointment );
						}
						$notifications->send_appointment_change_notifications( $appointment, 'cancelled' );
						break;
				}
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[MP Agenda] MP_Agenda_Async::run_jobs — exception non interceptée (action=%s, appointment_id=%d) : %s',
					$action,
					(int) ( $appointment['id'] ?? 0 ),
					$e->getMessage()
				) );
			}
		}
	}
}
