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

		$this->last_error = '';

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

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[MP Agenda SMS] Tentative d\'envoi vers %s (service=%s, sender=%s).', $to, $s['service_name'], $s['sender'] ) );

		$url = self::API_BASE . '/sms/' . rawurlencode( $s['service_name'] ) . '/jobs';

		$body = wp_json_encode(
			array(
				'charset'      => 'UTF-8',
				'receivers'    => array( $to ),
				'message'      => $message,
				'noStopClause' => true,
				'priority'     => 'high',
				'sender'       => $s['sender'],
			)
		);

		$timestamp = $this->get_ovh_time();
		$signature = $this->sign( $s['app_secret'], $s['consumer_key'], 'POST', $url, $body, $timestamp );

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
			$this->last_error = sprintf(
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

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[MP Agenda SMS] SMS envoyé à %s (job OVH : %s, crédits consommés : %s)',
			$to,
			( is_array( $decoded ) && isset( $decoded['ids'][0] ) ) ? $decoded['ids'][0] : 'n/c',
			( is_array( $decoded ) && isset( $decoded['totalCreditsRemoved'] ) ) ? $decoded['totalCreditsRemoved'] : 'n/c'
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

			$sent = $this->send_sms( $appointment['client_phone'], $this->render_message( $template, $appointment ) );

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
	 * @param string $template    Modèle.
	 * @param array  $appointment Rendez-vous (avec technician_name / service_name du JOIN).
	 * @return string
	 */
	public function render_message( $template, $appointment ) {
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

		$replacements = array(
			'{client_name}'  => $appointment['client_name'] ?? '',
			'{date}'         => $date_fr,
			'{heure}'        => $date->format( 'H:i' ),
			'{service}'      => $service,
			'{commercial}'   => $appointment['technician_name'] ?? '',
			'{company_name}' => $settings['company_name'] ?? get_bloginfo( 'name' ),
			'{manage_url}'   => MP_Agenda_Calendar_Links::manage_url( (int) ( $appointment['id'] ?? 0 ) ),
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

		return $this->send_sms( $phone, '[Test] ' . $this->render_message( $s['message_j1'], $appointment ) );
	}
}
