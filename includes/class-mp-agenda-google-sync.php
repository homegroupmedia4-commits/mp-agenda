<?php
/**
 * Gère la synchronisation bidirectionnelle avec Google Agenda.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Google_Sync.
 */
class MP_Agenda_Google_Sync {

	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const API_BASE  = 'https://www.googleapis.com/calendar/v3';
	const SCOPES    = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events';

	/**
	 * Enregistre les hooks (cron + admin-ajax OAuth).
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'mp_agenda_google_sync_cron', array( $this, 'sync_all' ) );
		add_action( 'wp_ajax_mp_agenda_google_connect', array( $this, 'handle_connect' ) );
		add_action( 'wp_ajax_mp_agenda_google_callback', array( $this, 'handle_callback' ) );
	}

	/* ---------------------------------------------------------------------
	 * OAuth 2.0
	 * ------------------------------------------------------------------- */

	/**
	 * Initie le flux OAuth : redirige l'admin vers l'écran de consentement Google.
	 *
	 * @return void
	 */
	public function handle_connect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'mp-agenda' ) );
		}
		check_admin_referer( 'mp_agenda_google_connect' );

		$technician_id = isset( $_GET['technician_id'] ) ? absint( $_GET['technician_id'] ) : 0;
		$client_id     = get_option( 'mp_agenda_google_client_id' );

		if ( ! $technician_id || ! $client_id ) {
			wp_die( esc_html__( 'Configuration Google incomplète.', 'mp-agenda' ) );
		}

		$state = wp_json_encode(
			array(
				'technician_id' => $technician_id,
				'nonce'         => wp_create_nonce( 'mp_agenda_google_oauth_' . $technician_id ),
			)
		);

		$params = array(
			'client_id'              => $client_id,
			'redirect_uri'           => admin_url( 'admin-ajax.php?action=mp_agenda_google_callback' ),
			'response_type'          => 'code',
			'scope'                  => self::SCOPES,
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		wp_redirect( self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/**
	 * Callback OAuth appelé par Google après consentement : échange le code contre les tokens.
	 *
	 * URL enregistrée dans la Google Cloud Console :
	 * {site_url}/wp-admin/admin-ajax.php?action=mp_agenda_google_callback
	 *
	 * @return void
	 */
	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'mp-agenda' ) );
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? json_decode( wp_unslash( $_GET['state'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $code || ! is_array( $state ) || empty( $state['technician_id'] ) || empty( $state['nonce'] ) ) {
			wp_die( esc_html__( 'Réponse Google invalide.', 'mp-agenda' ) );
		}

		$technician_id = absint( $state['technician_id'] );

		if ( ! wp_verify_nonce( $state['nonce'], 'mp_agenda_google_oauth_' . $technician_id ) ) {
			wp_die( esc_html__( 'Session expirée, merci de recommencer la connexion.', 'mp-agenda' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => get_option( 'mp_agenda_google_client_id' ),
					'client_secret' => get_option( 'mp_agenda_google_client_secret' ),
					'redirect_uri'  => admin_url( 'admin-ajax.php?action=mp_agenda_google_callback' ),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			wp_die( esc_html__( 'Impossible de récupérer le jeton d\'accès Google.', 'mp-agenda' ) );
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) ( $body['expires_in'] ?? 3600 ) );

		$data = array(
			'google_access_token'     => $this->encrypt( $body['access_token'] ),
			'google_token_expires_at' => $expires_at,
			'google_calendar_id'      => 'primary',
		);

		if ( ! empty( $body['refresh_token'] ) ) {
			$data['google_refresh_token'] = $this->encrypt( $body['refresh_token'] );
		}

		MP_Agenda_DB::save_technician( $data, $technician_id );

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-technicians', 'mp_agenda_notice' => 'connected' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Retourne un access_token valide pour un technicien, en le renouvelant si besoin.
	 *
	 * @param array $technician Données du technicien.
	 * @return string|null
	 */
	private function get_valid_access_token( $technician ) {
		if ( empty( $technician['google_refresh_token'] ) ) {
			return null;
		}

		$expires_at = $technician['google_token_expires_at'] ? strtotime( $technician['google_token_expires_at'] ) : 0;

		if ( $expires_at > time() + 60 && ! empty( $technician['google_access_token'] ) ) {
			return $this->decrypt( $technician['google_access_token'] );
		}

		return $this->refresh_access_token( $technician );
	}

	/**
	 * Renouvelle l'access_token via le refresh_token stocké.
	 *
	 * @param array $technician Données du technicien.
	 * @return string|null
	 */
	private function refresh_access_token( $technician ) {
		$refresh_token = $this->decrypt( $technician['google_refresh_token'] );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'refresh_token' => $refresh_token,
					'client_id'     => get_option( 'mp_agenda_google_client_id' ),
					'client_secret' => get_option( 'mp_agenda_google_client_secret' ),
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return null;
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) ( $body['expires_in'] ?? 3600 ) );

		MP_Agenda_DB::save_technician(
			array(
				'google_access_token'     => $this->encrypt( $body['access_token'] ),
				'google_token_expires_at' => $expires_at,
			),
			$technician['id']
		);

		return $body['access_token'];
	}

	/* ---------------------------------------------------------------------
	 * Synchronisation Plugin -> Google
	 * ------------------------------------------------------------------- */

	/**
	 * Pousse un rendez-vous vers Google Agenda (création ou mise à jour de l'event).
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return void
	 */
	public function push_appointment( $appointment ) {
		$technician = MP_Agenda_DB::get_technician( $appointment['technician_id'] );

		if ( ! $technician || empty( $technician['google_refresh_token'] ) ) {
			return;
		}

		$access_token = $this->get_valid_access_token( $technician );
		if ( ! $access_token ) {
			return;
		}

		$calendar_id = $technician['google_calendar_id'] ?: 'primary';

		$event = array(
			'summary'     => sprintf( '%s — %s', $appointment['client_name'], $appointment['intervention_type'] ),
			'description' => $this->build_event_description( $appointment ),
			'location'    => $appointment['client_address'],
			'start'       => array(
				'dateTime' => $this->to_rfc3339( $appointment['start_datetime'] ),
				'timeZone' => wp_timezone_string(),
			),
			'end'         => array(
				'dateTime' => $this->to_rfc3339( $appointment['end_datetime'] ),
				'timeZone' => wp_timezone_string(),
			),
		);

		if ( 'cancelled' === $appointment['status'] ) {
			if ( ! empty( $appointment['google_event_id'] ) ) {
				$this->delete_event( $appointment );
			}
			return;
		}

		if ( ! empty( $appointment['google_event_id'] ) ) {
			$response = wp_remote_request(
				self::API_BASE . "/calendars/{$calendar_id}/events/{$appointment['google_event_id']}",
				array(
					'method'  => 'PATCH',
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $event ),
				)
			);
		} else {
			$response = wp_remote_post(
				self::API_BASE . "/calendars/{$calendar_id}/events",
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $event ),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['id'] ) && empty( $appointment['google_event_id'] ) ) {
			MP_Agenda_DB::save_appointment( array( 'google_event_id' => $body['id'] ), $appointment['id'] );
		}
	}

	/**
	 * Supprime l'événement Google associé à un rendez-vous.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return void
	 */
	public function delete_event( $appointment ) {
		$technician = MP_Agenda_DB::get_technician( $appointment['technician_id'] );

		if ( ! $technician || empty( $technician['google_refresh_token'] ) || empty( $appointment['google_event_id'] ) ) {
			return;
		}

		$access_token = $this->get_valid_access_token( $technician );
		if ( ! $access_token ) {
			return;
		}

		$calendar_id = $technician['google_calendar_id'] ?: 'primary';

		wp_remote_request(
			self::API_BASE . "/calendars/{$calendar_id}/events/{$appointment['google_event_id']}",
			array(
				'method'  => 'DELETE',
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);
	}

	/**
	 * Construit la description texte de l'événement Google Agenda.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return string
	 */
	private function build_event_description( $appointment ) {
		$lines = array(
			'Client : ' . $appointment['client_name'],
			'Téléphone : ' . $appointment['client_phone'],
		);
		if ( ! empty( $appointment['client_email'] ) ) {
			$lines[] = 'Email : ' . $appointment['client_email'];
		}
		if ( ! empty( $appointment['surface'] ) ) {
			$lines[] = 'Surface : ' . $appointment['surface'];
		}
		if ( ! empty( $appointment['internal_notes'] ) ) {
			$lines[] = 'Notes internes : ' . $appointment['internal_notes'];
		}
		return implode( "\n", $lines );
	}

	/* ---------------------------------------------------------------------
	 * Synchronisation Google -> Plugin (cron toutes les 5 minutes)
	 * ------------------------------------------------------------------- */

	/**
	 * Synchronise tous les techniciens connectés à Google Agenda.
	 *
	 * @return void
	 */
	public function sync_all() {
		$technicians = MP_Agenda_DB::get_technicians( true );

		foreach ( $technicians as $technician ) {
			if ( empty( $technician['google_refresh_token'] ) ) {
				continue;
			}
			$this->sync_technician( $technician );
		}
	}

	/**
	 * Synchronise le calendrier Google d'un technicien vers les tables locales.
	 *
	 * @param array $technician Données du technicien.
	 * @return void
	 */
	private function sync_technician( $technician ) {
		$access_token = $this->get_valid_access_token( $technician );
		if ( ! $access_token ) {
			return;
		}

		$calendar_id  = $technician['google_calendar_id'] ?: 'primary';
		$option_key   = 'mp_agenda_google_last_sync_' . $technician['id'];
		$last_sync    = get_option( $option_key );
		$updated_min  = $last_sync ? $last_sync : gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) );
		$now          = gmdate( 'Y-m-d\TH:i:s\Z' );

		$params = array(
			'singleEvents' => 'true',
			'updatedMin'   => $updated_min,
			'showDeleted'  => 'true',
			'maxResults'   => 250,
		);

		$response = wp_remote_get(
			self::API_BASE . "/calendars/{$calendar_id}/events?" . http_build_query( $params ),
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) )
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
			update_option( $option_key, $now );
			return;
		}

		foreach ( $body['items'] as $event ) {
			$this->reconcile_event( $technician, $event );
		}

		update_option( $option_key, $now );
	}

	/**
	 * Réconcilie un événement Google avec les tables locales (RDV ou créneaux bloqués).
	 *
	 * @param array $technician Données du technicien.
	 * @param array $event      Événement Google Agenda.
	 * @return void
	 */
	private function reconcile_event( $technician, $event ) {
		if ( empty( $event['id'] ) ) {
			return;
		}

		// Événement supprimé côté Google : on supprime le RDV ou le créneau bloqué local.
		if ( 'cancelled' === ( $event['status'] ?? '' ) ) {
			$appointment = MP_Agenda_DB::get_appointment_by_google_id( $event['id'] );
			if ( $appointment ) {
				MP_Agenda_DB::save_appointment( array( 'status' => 'cancelled' ), $appointment['id'] );
				return;
			}
			MP_Agenda_DB::delete_blocked_slot_by_google_id( $event['id'] );
			return;
		}

		// Ignore les événements toute la journée (pas de créneau horaire exploitable).
		if ( empty( $event['start']['dateTime'] ) || empty( $event['end']['dateTime'] ) ) {
			return;
		}

		$start = gmdate( 'Y-m-d H:i:s', strtotime( $event['start']['dateTime'] ) );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( $event['end']['dateTime'] ) );

		// Un événement déjà lié à un RDV créé depuis le plugin : on met juste à jour les horaires.
		$appointment = MP_Agenda_DB::get_appointment_by_google_id( $event['id'] );
		if ( $appointment ) {
			MP_Agenda_DB::save_appointment(
				array(
					'start_datetime' => $start,
					'end_datetime'   => $end,
				),
				$appointment['id']
			);
			return;
		}

		// Événement créé/modifié directement dans Google Agenda : on bloque simplement le créneau
		// (pas assez d'informations client pour créer un rendez-vous complet).
		$blocked = MP_Agenda_DB::get_blocked_slot_by_google_id( $event['id'] );
		$data    = array(
			'technician_id'   => $technician['id'],
			'start_datetime'  => $start,
			'end_datetime'    => $end,
			'reason'          => sanitize_text_field( $event['summary'] ?? __( 'Google Agenda', 'mp-agenda' ) ),
			'google_event_id' => $event['id'],
		);

		if ( $blocked ) {
			MP_Agenda_DB::update_blocked_slot_by_google_id( $event['id'], $data );
		} else {
			MP_Agenda_DB::create_blocked_slot( $data );
		}
	}

	/* ---------------------------------------------------------------------
	 * Utilitaires
	 * ------------------------------------------------------------------- */

	/**
	 * Convertit une date MySQL en format RFC3339 attendu par l'API Google.
	 *
	 * @param string $mysql_datetime Date au format Y-m-d H:i:s.
	 * @return string
	 */
	private function to_rfc3339( $mysql_datetime ) {
		$date = new DateTime( $mysql_datetime, wp_timezone() );
		return $date->format( 'c' );
	}

	/**
	 * Chiffre une chaîne (tokens Google) avant stockage en base.
	 *
	 * @param string $value Valeur en clair.
	 * @return string
	 */
	private function encrypt( $value ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}

		$key = $this->get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $value, 'aes-256-cbc', $key, 0, $iv );

		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Déchiffre une chaîne stockée en base.
	 *
	 * @param string $value Valeur chiffrée.
	 * @return string
	 */
	private function decrypt( $value ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}

		$key      = $this->get_encryption_key();
		$raw      = base64_decode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$iv       = substr( $raw, 0, 16 );
		$payload  = substr( $raw, 16 );

		$decrypted = openssl_decrypt( $payload, 'aes-256-cbc', $key, 0, $iv );

		return false === $decrypted ? '' : $decrypted;
	}

	/**
	 * Retourne la clé de chiffrement des tokens Google.
	 *
	 * Utilise la constante MP_AGENDA_ENCRYPTION_KEY définie dans wp-config.php
	 * si disponible, sinon se replie sur les salts d'authentification de WordPress.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		if ( defined( 'MP_AGENDA_ENCRYPTION_KEY' ) && MP_AGENDA_ENCRYPTION_KEY ) {
			return hash( 'sha256', MP_AGENDA_ENCRYPTION_KEY, true );
		}

		$secret = defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : 'mp-agenda-fallback-key';
		return hash( 'sha256', $secret, true );
	}
}
