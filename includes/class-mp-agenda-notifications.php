<?php
/**
 * Gère l'envoi des notifications email (confirmation client et technicien).
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Notifications.
 */
class MP_Agenda_Notifications {

	/**
	 * Enregistre le hook public permettant au client d'annuler son RDV depuis l'email.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_mp_agenda_cancel_appointment', array( $this, 'handle_public_cancellation' ) );
		add_action( 'wp_ajax_mp_agenda_cancel_appointment', array( $this, 'handle_public_cancellation' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_mail_failure' ) );
	}

	/**
	 * Journalise les échecs d'envoi de wp_mail() (ex. SMTP indisponible, "From"
	 * refusé par l'hébergeur). wp_mail() échoue silencieusement par défaut : sans ce
	 * log, un email non envoyé est indiscernable d'un email envoyé mais jamais reçu.
	 *
	 * @param WP_Error $error Erreur remontée par PHPMailer.
	 * @return void
	 */
	public function log_mail_failure( $error ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[MP Agenda] wp_mail a échoué : ' . $error->get_error_message() );
	}

	/**
	 * Envoie les emails de confirmation (client + technicien) pour un rendez-vous.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return void
	 */
	public function send_appointment_notifications( $appointment ) {
		$settings   = get_option( 'mp_agenda_settings', array() );
		$technician = MP_Agenda_DB::get_technician( $appointment['technician_id'] );

		if ( ! empty( $settings['notify_client'] ) && ! empty( $appointment['client_email'] ) ) {
			$this->send_client_email( $appointment, $technician, $settings );
		}

		if ( ! empty( $settings['notify_technician'] ) && $technician && ! empty( $technician['email'] ) ) {
			$this->send_technician_email( $appointment, $technician, $settings );
		}
	}

	/**
	 * Envoie l'email de confirmation au client.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @param array $technician  Données du technicien.
	 * @param array $settings    Réglages généraux du plugin.
	 * @return void
	 */
	private function send_client_email( $appointment, $technician, $settings ) {
		$date = new DateTime( $appointment['start_datetime'] );

		$subject = sprintf(
			/* translators: 1: nom entreprise, 2: date, 3: heure */
			__( 'Votre rendez-vous %1$s — %2$s à %3$s', 'mp-agenda' ),
			$settings['company_name'] ?? get_bloginfo( 'name' ),
			$date->format( 'd/m/Y' ),
			$date->format( 'H:i' )
		);

		$cancel_url = add_query_arg(
			array(
				'action' => 'mp_agenda_cancel_appointment',
				'token'  => $appointment['cancel_token'],
			),
			admin_url( 'admin-ajax.php' )
		);

		$body = $this->render_template(
			'confirmation-client.php',
			array(
				'appointment'    => $appointment,
				'technician'     => $technician,
				'settings'       => $settings,
				'date'           => $date,
				'cancel_url'     => $cancel_url,
				'manage_url'     => MP_Agenda_Calendar_Links::manage_url( $appointment['id'] ),
				'calendar_links' => MP_Agenda_Calendar_Links::all_links( $appointment ),
			)
		);

		$this->mail( $appointment['client_email'], $subject, $body, $settings );
	}

	/**
	 * Envoie l'email de notification au technicien.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @param array $technician  Données du technicien.
	 * @param array $settings    Réglages généraux du plugin.
	 * @return void
	 */
	private function send_technician_email( $appointment, $technician, $settings ) {
		$date = new DateTime( $appointment['start_datetime'] );

		$subject = sprintf(
			/* translators: 1: nom client, 2: date, 3: heure */
			__( 'Nouveau RDV — %1$s — %2$s à %3$s', 'mp-agenda' ),
			$appointment['client_name'],
			$date->format( 'd/m/Y' ),
			$date->format( 'H:i' )
		);

		$body = $this->render_template(
			'confirmation-technician.php',
			array(
				'appointment' => $appointment,
				'technician'  => $technician,
				'settings'    => $settings,
				'date'        => $date,
			)
		);

		$this->mail( $technician['email'], $subject, $body, $settings );
	}

	/**
	 * Envoie les emails de suivi (client + commercial) lorsqu'un rendez-vous est
	 * modifié ou annulé depuis la page publique "Gérer mon rendez-vous".
	 *
	 * @param array  $appointment Données du rendez-vous (après mise à jour).
	 * @param string $change_type 'modified' (reprogrammé) ou 'cancelled' (annulé).
	 * @return void
	 */
	public function send_appointment_change_notifications( $appointment, $change_type ) {
		if ( empty( $appointment ) ) {
			return;
		}

		$change_type = in_array( $change_type, array( 'modified', 'cancelled' ), true ) ? $change_type : 'modified';

		$settings   = get_option( 'mp_agenda_settings', array() );
		$technician = MP_Agenda_DB::get_technician( $appointment['technician_id'] );
		$date       = new DateTime( $appointment['start_datetime'] );

		$company_name = $settings['company_name'] ?? get_bloginfo( 'name' );

		if ( ! empty( $settings['notify_client'] ) && ! empty( $appointment['client_email'] ) ) {
			if ( 'cancelled' === $change_type ) {
				/* translators: %s: nom de l'entreprise */
				$subject = sprintf( __( 'Annulation de votre rendez-vous %s', 'mp-agenda' ), $company_name );
			} else {
				/* translators: 1: nom entreprise, 2: date, 3: heure */
				$subject = sprintf( __( 'Votre rendez-vous %1$s a été modifié — %2$s à %3$s', 'mp-agenda' ), $company_name, $date->format( 'd/m/Y' ), $date->format( 'H:i' ) );
			}

			$body = $this->render_template(
				'change-client.php',
				array(
					'appointment' => $appointment,
					'technician'  => $technician,
					'settings'    => $settings,
					'date'        => $date,
					'change_type' => $change_type,
					'manage_url'  => MP_Agenda_Calendar_Links::manage_url( $appointment['id'] ),
					'calendar_links' => MP_Agenda_Calendar_Links::all_links( $appointment ),
				)
			);

			$this->mail( $appointment['client_email'], $subject, $body, $settings );
		}

		if ( ! empty( $settings['notify_technician'] ) && $technician && ! empty( $technician['email'] ) ) {
			if ( 'cancelled' === $change_type ) {
				/* translators: 1: nom client, 2: date, 3: heure */
				$subject = sprintf( __( 'RDV annulé — %1$s — %2$s à %3$s', 'mp-agenda' ), $appointment['client_name'], $date->format( 'd/m/Y' ), $date->format( 'H:i' ) );
			} else {
				/* translators: 1: nom client, 2: date, 3: heure */
				$subject = sprintf( __( 'RDV modifié — %1$s — %2$s à %3$s', 'mp-agenda' ), $appointment['client_name'], $date->format( 'd/m/Y' ), $date->format( 'H:i' ) );
			}

			$body = $this->render_template(
				'change-technician.php',
				array(
					'appointment' => $appointment,
					'technician'  => $technician,
					'settings'    => $settings,
					'date'        => $date,
					'change_type' => $change_type,
				)
			);

			$this->mail( $technician['email'], $subject, $body, $settings );
		}
	}

	/* ---------------------------------------------------------------------
	 * Rappel 24 h avant le rendez-vous
	 * ------------------------------------------------------------------- */

	/**
	 * Callback du cron horaire mp_agenda_send_reminders_cron.
	 *
	 * Parcourt les RDV confirmés dont le début tombe dans une fenêtre glissante de
	 * 2 h (maintenant +23 h → +25 h) pour absorber un cron WordPress déclenché en
	 * retard, et envoie un rappel unique par RDV (flag reminder_sent en base).
	 *
	 * @return void
	 */
	public function send_due_reminders() {
		$settings = get_option( 'mp_agenda_settings', array() );

		// notify_reminder : activé par défaut tant que la clé n'a jamais été
		// enregistrée (case "cochée par défaut" côté réglages).
		if ( array_key_exists( 'notify_reminder', $settings ) && empty( $settings['notify_reminder'] ) ) {
			return;
		}

		$now  = new DateTime( 'now', wp_timezone() );
		$from = ( clone $now )->modify( '+23 hours' )->format( 'Y-m-d H:i:s' );
		$to   = ( clone $now )->modify( '+25 hours' )->format( 'Y-m-d H:i:s' );

		$appointments = MP_Agenda_DB::get_appointments_needing_reminder( $from, $to );

		foreach ( $appointments as $appointment ) {
			$sent = $this->send_reminder_email( $appointment );

			// On marque toujours le RDV comme traité (même sans email client) pour ne
			// jamais renvoyer de rappel au passage suivant du cron.
			MP_Agenda_DB::mark_reminder_sent( (int) $appointment['id'] );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[MP Agenda] Rappel RDV #%d (%s) : %s',
				(int) $appointment['id'],
				$appointment['start_datetime'],
				$sent ? 'email envoyé à ' . $appointment['client_email'] : 'non envoyé (pas d\'email client ou statut non confirmé)'
			) );
		}
	}

	/**
	 * Envoie l'email de rappel au client pour un rendez-vous donné.
	 *
	 * @param array $appointment Données du rendez-vous (avec technician_name / service_name du JOIN).
	 * @return bool True si l'email a été transmis à wp_mail(), false sinon.
	 */
	public function send_reminder_email( $appointment ) {
		if ( empty( $appointment['client_email'] ) ) {
			return false;
		}

		if ( 'confirmed' !== ( $appointment['status'] ?? '' ) ) {
			return false;
		}

		$settings   = get_option( 'mp_agenda_settings', array() );
		$technician = ! empty( $appointment['technician_id'] ) ? MP_Agenda_DB::get_technician( $appointment['technician_id'] ) : null;
		$date       = new DateTime( $appointment['start_datetime'] );

		$subject = sprintf(
			/* translators: 1: heure du RDV, 2: nom de l'entreprise */
			__( 'Rappel — Votre rendez-vous demain à %1$s — %2$s', 'mp-agenda' ),
			$date->format( 'H:i' ),
			$settings['company_name'] ?? get_bloginfo( 'name' )
		);

		$cancel_url = ! empty( $appointment['cancel_token'] )
			? add_query_arg(
				array(
					'action' => 'mp_agenda_cancel_appointment',
					'token'  => $appointment['cancel_token'],
				),
				admin_url( 'admin-ajax.php' )
			)
			: '';

		$body = $this->render_template(
			'reminder-client.php',
			array(
				'appointment'    => $appointment,
				'technician'     => $technician,
				'settings'       => $settings,
				'date'           => $date,
				'cancel_url'     => $cancel_url,
				'manage_url'     => MP_Agenda_Calendar_Links::manage_url( $appointment['id'] ),
				'calendar_links' => MP_Agenda_Calendar_Links::all_links( $appointment ),
			)
		);

		$this->mail( $appointment['client_email'], $subject, $body, $settings );

		return true;
	}

	/**
	 * Envoie un rappel de test (données fictives, RDV fixé à demain) à l'adresse
	 * fournie, pour vérifier le rendu du template depuis la page Réglages.
	 *
	 * @param string $to Adresse email destinataire.
	 * @return bool
	 */
	public function send_test_reminder( $to ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$settings = get_option( 'mp_agenda_settings', array() );

		$start = new DateTime( 'tomorrow 10:00', wp_timezone() );
		$end   = ( clone $start )->modify( '+60 minutes' );

		$appointment = array(
			'id'                => 0,
			'client_name'       => __( 'Client test', 'mp-agenda' ),
			'client_email'      => $to,
			'client_phone'      => '06 12 34 56 78',
			'client_address'    => __( '1 rue de l\'Exemple, 75000 Paris', 'mp-agenda' ),
			'technician_name'   => __( 'Commercial test', 'mp-agenda' ),
			'service_name'      => __( 'Visite technique', 'mp-agenda' ),
			'intervention_type' => '',
			'start_datetime'    => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'      => $end->format( 'Y-m-d H:i:s' ),
			'duration'          => 60,
			'status'            => 'confirmed',
			'cancel_token'      => '',
		);

		$subject = '[Test] ' . sprintf(
			/* translators: 1: heure du RDV, 2: nom de l'entreprise */
			__( 'Rappel — Votre rendez-vous demain à %1$s — %2$s', 'mp-agenda' ),
			$start->format( 'H:i' ),
			$settings['company_name'] ?? get_bloginfo( 'name' )
		);

		$body = $this->render_template(
			'reminder-client.php',
			array(
				'appointment'    => $appointment,
				'technician'     => null,
				'settings'       => $settings,
				'date'           => $start,
				'cancel_url'     => '',
				'manage_url'     => '',
				'calendar_links' => MP_Agenda_Calendar_Links::all_links( $appointment ),
			)
		);

		$this->mail( $to, $subject, $body, $settings );

		return true;
	}

	/**
	 * Envoie un email HTML via wp_mail().
	 *
	 * @param string $to      Destinataire.
	 * @param string $subject Objet.
	 * @param string $body    Corps HTML.
	 * @param array  $settings Réglages généraux du plugin.
	 * @return void
	 */
	private function mail( $to, $subject, $body, $settings ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( ! empty( $settings['notification_email'] ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $settings['company_name'] ?? get_bloginfo( 'name' ), $settings['notification_email'] );
		}

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Charge et rend un template email PHP avec les variables fournies.
	 *
	 * @param string $template Nom du fichier de template.
	 * @param array  $vars     Variables à extraire dans le scope du template.
	 * @return string
	 */
	private function render_template( $template, $vars ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract

		ob_start();
		require MP_AGENDA_PLUGIN_DIR . 'templates/emails/' . $template;
		return ob_get_clean();
	}

	/**
	 * Traite la demande d'annulation d'un rendez-vous depuis le lien envoyé par email.
	 *
	 * @return void
	 */
	public function handle_public_cancellation() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! $token ) {
			wp_die( esc_html__( 'Lien d\'annulation invalide.', 'mp-agenda' ) );
		}

		$appointment = MP_Agenda_DB::get_appointment_by_token( $token );

		if ( ! $appointment ) {
			wp_die( esc_html__( 'Rendez-vous introuvable ou déjà annulé.', 'mp-agenda' ) );
		}

		MP_Agenda_DB::save_appointment( array( 'status' => 'cancelled' ), $appointment['id'] );

		if ( ! empty( $appointment['google_event_id'] ) ) {
			$google_sync = new MP_Agenda_Google_Sync();
			$google_sync->delete_event( $appointment );
		}

		wp_die(
			esc_html__( 'Votre rendez-vous a bien été annulé. Nous restons à votre disposition pour en reprogrammer un autre.', 'mp-agenda' ),
			esc_html__( 'Rendez-vous annulé', 'mp-agenda' ),
			array( 'response' => 200 )
		);
	}
}
