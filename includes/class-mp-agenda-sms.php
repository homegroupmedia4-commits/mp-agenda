<?php
/**
 * Envoi de SMS de rappel via l'API OVH SMS.
 *
 * Authentification OVH par signature (X-Ovh-Application / X-Ovh-Timestamp /
 * X-Ovh-Consumer / X-Ovh-Signature) — voir sign(). Aucune dépendance externe :
 * uniquement wp_remote_get() / wp_remote_post().
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_SMS.
 */
class MP_Agenda_SMS {

	/**
	 * Racine de l'API OVH (Europe).
	 *
	 * @var string
	 */
	const API_BASE = 'https://eu.api.ovh.com/1.0';

	/**
	 * Valeurs par défaut des réglages SMS.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'      => 0,
			'service_name' => 'sms-ml1085990-1',
			'app_key'      => '',
			'app_secret'   => '',
			'consumer_key' => '',
			'sender'       => 'MP RENOV',
			'reminder_j3'  => 1,
			'reminder_j1'  => 1,
			'message_j3'   => 'Rappel : vous avez un RDV le {date} à {heure} avec {company_name}. Pour modifier : {manage_url}',
			'message_j1'   => 'Rappel : votre RDV est demain à {heure} avec {company_name}. Pour modifier : {manage_url}',
		);
	}

	/**
	 * Dernière erreur rencontrée (message lisible), pour l'affichage côté admin.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Code HTTP de la dernière réponse OVH (0 = erreur réseau / pas d'appel).
	 *
	 * @var int
	 */
	private $last_http_code = 0;

	/**
	 * Retourne les réglages SMS fusionnés avec les valeurs par défaut.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return wp_parse_args( (array) get_option( 'mp_agenda_sms_settings', array() ), self::defaults() );
	}

	/**
	 * Message décrivant la dernière erreur (chaîne vide si aucune).
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Liste, en clair, des réglages SMS manquants (tableau vide = configuration complète).
	 *
	 * Distingue explicitement "option jamais enregistrée" du cas "option enregistrée
	 * mais champ vide", pour lever l'ambiguïté la plus fréquente : réglages saisis
	 * dans le formulaire mais bouton « Enregistrer » jamais cliqué.
	 *
	 * @return string[]
	 */
	public function get_config_issues() {
		$issues = array();

		if ( false === get_option( 'mp_agenda_sms_settings', false ) ) {
			$issues[] = __( 'les réglages SMS n\'ont jamais été enregistrés (cliquez sur « Enregistrer » dans l\'onglet SMS)', 'mp-agenda' );
			return $issues;
		}

		$s = self::get_settings();

		if ( empty( $s['enabled'] ) ) {
			$issues[] = __( 'la case « Activer les rappels SMS » est décochée', 'mp-agenda' );
		}
		if ( empty( $s['service_name'] ) ) {
			$issues[] = __( 'le service OVH SMS est vide', 'mp-agenda' );
		}
		if ( empty( $s['app_key'] ) ) {
			$issues[] = __( 'l\'Application Key est vide', 'mp-agenda' );
		}
		if ( empty( $s['app_secret'] ) ) {
			$issues[] = __( 'l\'Application Secret est vide', 'mp-agenda' );
		}
		if ( empty( $s['consumer_key'] ) ) {
			$issues[] = __( 'la Consumer Key est vide', 'mp-agenda' );
		}

		return $issues;
	}

	/**
	 * Les SMS sont-ils utilisables ? (case activée ET service + 3 clés API renseignés)
	 *
	 * @return bool
	 */
	public function is_configured() {
		return array() === $this->get_config_issues();
	}

	/* ---------------------------------------------------------------------
	 * Formatage du numéro
	 * ------------------------------------------------------------------- */

	/**
	 * Formate un numéro de téléphone français au format international (+33…).
	 * 06… → +336…, 07… → +337…. Un numéro déjà international est conservé tel quel.
	 *
	 * @param string $number Numéro brut.
	 * @return string Numéro au format +33… ou chaîne vide si non exploitable.
	 */
	public static function format_phone( $number ) {
		$digits = preg_replace( '/[^0-9+]/', '', (string) $number );

		if ( '' === $digits || '+' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '+' ) ) {
			return $digits;
		}
		if ( 0 === strpos( $digits, '0033' ) ) {
			return '+' . substr( $digits, 2 );
		}
		if ( 0 === strpos( $digits, '33' ) && strlen( $digits ) >= 11 ) {
			return '+' . $digits;
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			return '+33' . substr( $digits, 1 );
		}

		return '+33' . $digits;
	}

	/* ---------------------------------------------------------------------
	 * Appels API OVH
	 * ------------------------------------------------------------------- */

	/**
	 * Récupère le timestamp serveur OVH (compense un décalage d'horloge local).
	 * Repli silencieux sur time() en cas d'échec.
	 *
	 * @return int
	 */
	private function get_ovh_time() {
		$response = wp_remote_get( self::API_BASE . '/auth/time', array( 'timeout' => 10 ) );

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = trim( wp_remote_retrieve_body( $response ) );
			if ( is_numeric( $body ) ) {
				return (int) $body;
			}
		}

		return time();
	}

	/**
	 * Calcule la signature OVH d'une requête.
	 *
	 * @param string $app_secret   Application Secret.
	 * @param string $consumer_key Consumer Key.
	 * @param string $method       Méthode HTTP.
	 * @param string $url          URL complète appelée.
	 * @param string $body         Corps de la requête (chaîne vide pour un GET).
	 * @param int    $timestamp    Timestamp OVH.
	 * @return string
	 */
	private function sign( $app_secret, $consumer_key, $method, $url, $body, $timestamp ) {
		return '$1$' . sha1( $app_secret . '+' . $consumer_key . '+' . $method . '+' . $url . '+' . $body . '+' . $timestamp );
	}

	/**
	 * Envoie un SMS via l'API OVH.
	 *
	 * @param string $phone_number Numéro du destinataire (format libre, sera normalisé).
	 * @param string $message      Contenu du SMS.
	 * @return bool True si OVH a accepté l'envoi.
	 */
	public function send_sms( $phone_number, $message ) {
		$s = self::get_settings();

		$this->last_error     = '';
		$this->last_http_code = 0;

		$issues = $this->get_config_issues();
		if ( ! empty( $issues ) ) {
			$this->last_error = __( 'Configuration SMS incomplète : ', 'mp-agenda' ) . implode( ' ; ', $issues ) . '.';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] Envoi ignoré — ' . $this->last_error );
			return false;
		}

		$to = self::format_phone( $phone_number );
		if ( '' === $to ) {
			$this->last_error = sprintf(
				/* translators: %s: numéro fourni */
				__( 'Numéro de téléphone vide ou invalide (« %s »).', 'mp-agenda' ),
				$phone_number
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] Envoi ignoré — ' . $this->last_error );
			return false;
		}

		$sender                  = isset( $s['sender'] ) ? trim( (string) $s['sender'] ) : '';
		$use_sender_for_response = false;

		// OVH exige toujours un expéditeur. Si aucun n'est configuré, on récupère la
		// liste des expéditeurs validés sur le compte et on prend le premier.
		if ( '' === $sender ) {
			$available = $this->get_available_senders();

			if ( is_array( $available ) && ! empty( $available ) ) {
				$sender = (string) $available[0];
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[MP Agenda SMS] Aucun expéditeur configuré — 1er expéditeur OVH disponible utilisé : ' . $sender );
			} else {
				// Aucun expéditeur validé (liste vide) ou liste indisponible : plutôt
				// que d'échouer, on demande à OVH un numéro court virtuel via
				// "senderForResponse" (autorise aussi les réponses du client) et on
				// n'envoie PAS de clé "sender".
				$use_sender_for_response = true;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[MP Agenda SMS] Aucun expéditeur validé disponible — envoi via senderForResponse (numéro court OVH).' );
			}
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[MP Agenda SMS] Tentative d\'envoi vers %s (service=%s, sender=%s).',
			$to,
			$s['service_name'],
			'' !== $sender ? $sender : 'senderForResponse'
		) );

		$ok = $this->post_job( $s, $to, $message, $sender, $use_sender_for_response );

		// Retry : si OVH refuse l'expéditeur (HTTP 403 « pending validation » /
		// « does not exist » / …), on retente immédiatement avec un numéro court
		// virtuel (senderForResponse) au lieu de l'expéditeur nommé.
		if ( ! $ok && '' !== $sender && 403 === $this->last_http_code && $this->looks_like_sender_error( $this->last_error ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[MP Agenda SMS] Expéditeur « %s » refusé par OVH (HTTP 403) — nouvelle tentative via senderForResponse.', $sender ) );
			$ok = $this->post_job( $s, $to, $message, '', true );
		}

		return $ok;
	}

	/**
	 * L'erreur OVH concerne-t-elle l'expéditeur (nom refusé / en attente de validation) ?
	 *
	 * @param string $error Message d'erreur.
	 * @return bool
	 */
	private function looks_like_sender_error( $error ) {
		$error = strtolower( (string) $error );

		if ( false === strpos( $error, 'sender' ) ) {
			return false;
		}

		return (
			false !== strpos( $error, 'pending' )
			|| false !== strpos( $error, 'does not exist' )
			|| false !== strpos( $error, 'not valid' )
			|| false !== strpos( $error, 'invalid' )
			|| false !== strpos( $error, 'unknown' )
			|| false !== strpos( $error, 'disable' )
			|| false !== strpos( $error, 'refused' )
		);
	}

	/**
	 * L'envoi va-t-il passer par un numéro court virtuel OVH (senderForResponse)
	 * plutôt que par un expéditeur nommé ? C'est le cas lorsqu'aucun expéditeur
	 * n'est configuré ET qu'aucun expéditeur validé n'est disponible sur le
	 * compte. Utilisé pour adapter le message en amont (OVH refuse les URLs /
	 * téléphones / e-mails avec un numéro court).
	 *
	 * Remarque : ne couvre pas la nouvelle tentative déclenchée après le refus
	 * d'un expéditeur nommé — ce cas est traité dans post_job() via
	 * strip_contact_info().
	 *
	 * @return bool
	 */
	public function will_use_sender_for_response() {
		$s      = self::get_settings();
		$sender = isset( $s['sender'] ) ? trim( (string) $s['sender'] ) : '';

		if ( '' !== $sender ) {
			return false;
		}

		$available = $this->get_available_senders();

		return ! ( is_array( $available ) && ! empty( $available ) );
	}

	/**
	 * Retire d'un message les « coordonnées de contact » (URLs, adresses e-mail,
	 * numéros de téléphone) qu'OVH refuse lorsqu'on envoie via un numéro court
	 * virtuel (senderForResponse) — erreur HTTP 400 « cannot send contact
	 * information with shortcode ».
	 *
	 * @param string $message Message d'origine.
	 * @return string Message nettoyé.
	 */
	private function strip_contact_info( $message ) {
		$message = (string) $message;

		// URLs (http://, https://, www.).
		$message = preg_replace( '#\b(?:https?://|www\.)\S+#i', '', $message );

		// Adresses e-mail.
		$message = preg_replace( '/\b[^\s@]+@[^\s@]+\.[^\s@]+/', '', $message );

		// Numéros de téléphone : suites d'au moins 9 chiffres, éventuellement
		// séparées par des espaces, points, tirets ou barres (+ initial toléré).
		// Les dates, heures et prix (moins de chiffres) sont préservés.
		$message = preg_replace_callback(
			'/\+?\d[\d\s.\-\/]{6,}\d/',
			static function ( $matches ) {
				$digits = preg_replace( '/\D/', '', $matches[0] );
				return strlen( $digits ) >= 9 ? '' : $matches[0];
			},
			$message
		);

		// Nettoyage des espaces et de la ponctuation orpheline laissés derrière.
		$message = preg_replace( '/[ \t]{2,}/', ' ', $message );
		$message = preg_replace( '/\s+([.,;:!?])/', '$1', $message );
		$message = preg_replace( '/[\s:\x{2013}\x{2014}\-]+$/u', '', trim( $message ) );

		return trim( $message );
	}

	/**
	 * Envoie effectivement un job SMS à OVH (POST /sms/{service}/jobs) et analyse
	 * la réponse. Renseigne $this->last_error et $this->last_http_code.
	 *
	 * @param array  $s                   Réglages SMS.
	 * @param string $to                  Numéro destinataire normalisé.
	 * @param string $message             Contenu du SMS.
	 * @param string $sender              Nom d'expéditeur ('' = aucun).
	 * @param bool   $sender_for_response Utiliser un numéro court virtuel OVH (senderForResponse).
	 * @return bool True si OVH a accepté le job.
	 */
	private function post_job( $s, $to, $message, $sender, $sender_for_response ) {
		$url = self::API_BASE . '/sms/' . rawurlencode( $s['service_name'] ) . '/jobs';

		$payload = array(
			'charset'      => 'UTF-8',
			'receivers'    => array( $to ),
			'message'      => $message,
			'noStopClause' => true,
			'priority'     => 'high',
		);

		if ( '' !== $sender ) {
			// Expéditeur (configuré ou 1er expéditeur validé du compte).
			$payload['sender'] = $sender;
		} elseif ( $sender_for_response ) {
			// Aucun expéditeur validé : numéro court virtuel OVH.
			$payload['senderForResponse'] = true;
		}

		if ( $sender_for_response ) {
			// OVH rejette « cannot send contact information with shortcode »
			// lorsque noStopClause est true avec un numéro court virtuel :
			// la clause STOP est obligatoire dans ce cas.
			$payload['noStopClause'] = false;

			// Sur un numéro court virtuel, OVH refuse aussi les « coordonnées de
			// contact » (URLs, e-mails, téléphones) dans le message : on les
			// retire ici en dernier recours — notamment pour la nouvelle
			// tentative déclenchée après le refus d'un expéditeur nommé.
			$payload['message'] = $this->strip_contact_info( $payload['message'] );
		}

		$body = wp_json_encode( $payload );

		$timestamp = $this->get_ovh_time();
		$signature = $this->sign( $s['app_secret'], $s['consumer_key'], 'POST', $url, $body, $timestamp );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[MP Agenda SMS] Payload envoyé : ' . $body );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'     => 'application/json; charset=utf-8',
					'X-Ovh-Application' => $s['app_key'],
					'X-Ovh-Timestamp'  => (string) $timestamp,
					'X-Ovh-Consumer'   => $s['consumer_key'],
					'X-Ovh-Signature'  => $signature,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_http_code = 0;
			$this->last_error     = sprintf(
				/* translators: %s: message d'erreur réseau */
				__( 'Impossible de joindre l\'API OVH : %s', 'mp-agenda' ),
				$response->get_error_message()
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] Échec réseau vers OVH : ' . $response->get_error_message() );
			return false;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$rawbody = wp_remote_retrieve_body( $response );

		$this->last_http_code = $code;

		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = sprintf(
				/* translators: 1: code HTTP, 2: corps de la réponse OVH */
				__( 'OVH a répondu HTTP %1$d : %2$s', 'mp-agenda' ),
				$code,
				$rawbody
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[MP Agenda SMS] OVH a répondu HTTP %d : %s', $code, $rawbody ) );
			return false;
		}

		$decoded = json_decode( $rawbody, true );
		$invalid = ( is_array( $decoded ) && ! empty( $decoded['invalidReceivers'] ) ) ? (array) $decoded['invalidReceivers'] : array();

		if ( ! empty( $invalid ) ) {
			$this->last_error = sprintf(
				/* translators: %s: numéro refusé */
				__( 'OVH a refusé le numéro %s (destinataire invalide).', 'mp-agenda' ),
				$to
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[MP Agenda SMS] Destinataire refusé par OVH (%s) : %s', $to, $rawbody ) );
			return false;
		}

		// Un job accepté doit contenir au moins un id ; sinon OVH a "accepté" sans rien envoyer.
		if ( ! is_array( $decoded ) || empty( $decoded['ids'] ) ) {
			$this->last_error = sprintf(
				/* translators: %s: corps de la réponse OVH */
				__( 'Réponse OVH inattendue (aucun identifiant de job) : %s', 'mp-agenda' ),
				$rawbody
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] Réponse OVH sans id de job : ' . $rawbody );
			return false;
		}

		$this->last_error = '';

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[MP Agenda SMS] SMS envoyé à %s (job OVH : %s, crédits consommés : %s, expéditeur : %s)',
			$to,
			isset( $decoded['ids'][0] ) ? $decoded['ids'][0] : 'n/c',
			isset( $decoded['totalCreditsRemoved'] ) ? $decoded['totalCreditsRemoved'] : 'n/c',
			'' !== $sender ? $sender : 'senderForResponse'
		) );

		return true;
	}

	/**
	 * Récupère le nombre de crédits SMS restants sur le compte OVH.
	 *
	 * @return float|null Solde de crédits, ou null si indisponible.
	 */
	public function get_sms_credits() {
		$s = self::get_settings();

		if ( empty( $s['service_name'] ) || empty( $s['app_key'] ) || empty( $s['app_secret'] ) || empty( $s['consumer_key'] ) ) {
			return null;
		}

		$url       = self::API_BASE . '/sms/' . rawurlencode( $s['service_name'] );
		$timestamp = $this->get_ovh_time();
		$signature = $this->sign( $s['app_secret'], $s['consumer_key'], 'GET', $url, '', $timestamp );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'X-Ovh-Application' => $s['app_key'],
					'X-Ovh-Timestamp'  => (string) $timestamp,
					'X-Ovh-Consumer'   => $s['consumer_key'],
					'X-Ovh-Signature'  => $signature,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] get_sms_credits : réponse OVH invalide.' );
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return ( is_array( $decoded ) && isset( $decoded['creditsLeft'] ) ) ? (float) $decoded['creditsLeft'] : null;
	}

	/**
	 * Récupère la liste des expéditeurs (senders) déclarés / validés pour le
	 * service OVH SMS configuré.
	 *
	 * GET /sms/{serviceName}/senders — signé comme les autres appels.
	 *
	 * Résultat mis en cache 15 minutes (transient) pour ne pas interroger OVH à
	 * chaque SMS lors d'un lot de rappels ; $force = true ignore le cache
	 * (utilisé par le bouton « Rafraîchir » de la page Réglages).
	 *
	 * @param bool $force Forcer un appel API en ignorant le cache.
	 * @return string[]|null Liste des noms d'expéditeurs, ou null si l'appel a échoué
	 *                       (réseau, clés, HTTP ≠ 200). Un tableau vide signifie
	 *                       "aucun expéditeur déclaré".
	 */
	public function get_available_senders( $force = false ) {
		$s = self::get_settings();

		if ( empty( $s['service_name'] ) || empty( $s['app_key'] ) || empty( $s['app_secret'] ) || empty( $s['consumer_key'] ) ) {
			return null;
		}

		$cache_key = 'mp_agenda_sms_senders_' . md5( $s['service_name'] . '|' . $s['app_key'] );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$url       = self::API_BASE . '/sms/' . rawurlencode( $s['service_name'] ) . '/senders';
		$timestamp = $this->get_ovh_time();
		$signature = $this->sign( $s['app_secret'], $s['consumer_key'], 'GET', $url, '', $timestamp );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'X-Ovh-Application' => $s['app_key'],
					'X-Ovh-Timestamp'  => (string) $timestamp,
					'X-Ovh-Consumer'   => $s['consumer_key'],
					'X-Ovh-Signature'  => $signature,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[MP Agenda SMS] get_available_senders : réponse OVH invalide.' );
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		// OVH renvoie une liste de chaînes (["MP RENOV", "36180"]) ; on tolère aussi
		// une liste d'objets { "sender": "..." } au cas où l'API évoluerait.
		$senders = array();
		foreach ( $decoded as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$senders[] = trim( $item );
			} elseif ( is_array( $item ) && ! empty( $item['sender'] ) ) {
				$senders[] = (string) $item['sender'];
			}
		}

		$senders = array_values( array_unique( $senders ) );

		set_transient( $cache_key, $senders, 15 * MINUTE_IN_SECONDS );

		return $senders;
	}

	/* ---------------------------------------------------------------------
	 * Rappels SMS (cron horaire mp_agenda_send_reminders_cron)
	 * ------------------------------------------------------------------- */

	/**
	 * Callback additionnel du cron mp_agenda_send_reminders_cron : rappels SMS
	 * J-3 (fenêtre +71 h → +73 h) et J-1 (fenêtre +23 h → +25 h). Fenêtres de 2 h
	 * pour absorber un cron WordPress déclenché en retard.
	 *
	 * @return void
	 */
	public function send_due_sms_reminders() {
		if ( ! $this->is_configured() ) {
			return;
		}

		$s   = self::get_settings();
		$now = new DateTime( 'now', wp_timezone() );

		if ( ! empty( $s['reminder_j3'] ) ) {
			$this->process_sms_window(
				( clone $now )->modify( '+71 hours' )->format( 'Y-m-d H:i:s' ),
				( clone $now )->modify( '+73 hours' )->format( 'Y-m-d H:i:s' ),
				'j3',
				$s['message_j3']
			);
		}

		if ( ! empty( $s['reminder_j1'] ) ) {
			$this->process_sms_window(
				( clone $now )->modify( '+23 hours' )->format( 'Y-m-d H:i:s' ),
				( clone $now )->modify( '+25 hours' )->format( 'Y-m-d H:i:s' ),
				'j1',
				$s['message_j1']
			);
		}
	}

	/**
	 * Traite une fenêtre de rappel SMS (J-3 ou J-1).
	 *
	 * @param string $from     Début de fenêtre (Y-m-d H:i:s).
	 * @param string $to       Fin de fenêtre (Y-m-d H:i:s).
	 * @param string $which    'j3' ou 'j1'.
	 * @param string $template Modèle de message avec placeholders.
	 * @return void
	 */
	private function process_sms_window( $from, $to, $which, $template ) {
		$column = 'j3' === $which ? 'sms_reminder_j3_sent' : 'sms_reminder_j1_sent';

		// Envoi via numéro court OVH : le modèle ne doit pas contenir d'URL.
		$no_contact_info = $this->will_use_sender_for_response();

		foreach ( MP_Agenda_DB::get_confirmed_appointments_between( $from, $to ) as $appointment ) {
			if ( ! empty( $appointment[ $column ] ) ) {
				continue;
			}

			if ( empty( $appointment['client_phone'] ) ) {
				MP_Agenda_DB::mark_sms_reminder_sent( (int) $appointment['id'], $which );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[MP Agenda SMS] Rappel %s RDV #%d ignoré : pas de téléphone client.', strtoupper( $which ), (int) $appointment['id'] ) );
				continue;
			}

			$sent = $this->send_sms( $appointment['client_phone'], $this->render_message( $template, $appointment, $no_contact_info ) );

			// On marque toujours le rappel comme traité pour ne jamais en renvoyer un
			// deuxième au passage suivant du cron.
			MP_Agenda_DB::mark_sms_reminder_sent( (int) $appointment['id'], $which );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[MP Agenda SMS] Rappel %s RDV #%d (%s) : %s',
				strtoupper( $which ),
				(int) $appointment['id'],
				$appointment['start_datetime'],
				$sent ? 'envoyé à ' . $appointment['client_phone'] : 'échec'
			) );
		}
	}

	/**
	 * Remplace les placeholders d'un message SMS par les données d'un rendez-vous.
	 *
	 * Placeholders : {client_name} {date} {heure} {service} {commercial}
	 * {company_name} {manage_url}
	 *
	 * @param string $template          Modèle.
	 * @param array  $appointment       Rendez-vous (avec technician_name / service_name du JOIN).
	 * @param bool   $no_contact_info   True pour un envoi via numéro court OVH : {manage_url}
	 *                                  est alors remplacé par le nom de l'entreprise (OVH
	 *                                  refuse les URLs avec les numéros courts).
	 * @return string
	 */
	public function render_message( $template, $appointment, $no_contact_info = false ) {
		$settings = get_option( 'mp_agenda_settings', array() );
		$date     = new DateTime( $appointment['start_datetime'] );

		$months = array(
			1  => 'janvier',
			2  => 'février',
			3  => 'mars',
			4  => 'avril',
			5  => 'mai',
			6  => 'juin',
			7  => 'juillet',
			8  => 'août',
			9  => 'septembre',
			10 => 'octobre',
			11 => 'novembre',
			12 => 'décembre',
		);

		$date_fr = (int) $date->format( 'j' ) . ' ' . $months[ (int) $date->format( 'n' ) ] . ' ' . $date->format( 'Y' );
		$service = ! empty( $appointment['service_name'] ) ? $appointment['service_name'] : ( $appointment['intervention_type'] ?? '' );

		$company = $settings['company_name'] ?? get_bloginfo( 'name' );

		$replacements = array(
			'{client_name}'  => $appointment['client_name'] ?? '',
			'{date}'         => $date_fr,
			'{heure}'        => $date->format( 'H:i' ),
			'{service}'      => $service,
			'{commercial}'   => $appointment['technician_name'] ?? '',
			'{company_name}' => $company,
			'{manage_url}'   => $no_contact_info
				? $company
				: MP_Agenda_Calendar_Links::manage_url( (int) ( $appointment['id'] ?? 0 ) ),
		);

		return strtr( (string) $template, $replacements );
	}

	/**
	 * Envoie un SMS de test (données fictives, RDV fixé à demain) au numéro fourni.
	 *
	 * @param string $phone Numéro destinataire.
	 * @return bool
	 */
	public function send_test_sms( $phone ) {
		$s = self::get_settings();

		$appointment = array(
			'id'                => 0,
			'client_name'       => __( 'Client test', 'mp-agenda' ),
			'client_phone'      => $phone,
			'technician_name'   => __( 'Commercial test', 'mp-agenda' ),
			'service_name'      => __( 'Visite technique', 'mp-agenda' ),
			'intervention_type' => '',
			'start_datetime'    => ( new DateTime( 'tomorrow 10:30', wp_timezone() ) )->format( 'Y-m-d H:i:s' ),
		);

		if ( $this->will_use_sender_for_response() ) {
			// Numéro court OVH : aucun lien / téléphone / e-mail autorisé.
			$message = __( 'Ceci est un SMS test depuis MP Agenda. Si vous recevez ce message, la configuration est correcte.', 'mp-agenda' );
		} else {
			$message = '[Test] ' . $this->render_message( $s['message_j1'], $appointment );
		}

		return $this->send_sms( $phone, $message );
	}
}
