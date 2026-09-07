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
	const SCOPES    = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email';

	/**
	 * Journal de diagnostic TEMPORAIRE de la dernière synchronisation Google -> Plugin,
	 * accumulé par log() et renvoyé par force_google_sync() dans la réponse JSON pour
	 * pouvoir déboguer depuis la console navigateur sans accès aux logs serveur.
	 * À retirer une fois la stabilité confirmée en production.
	 *
	 * @var array
	 */
	private $debug_log = array();

	/**
	 * Ajoute une ligne au journal de diagnostic ET la trace dans le journal PHP (error_log).
	 *
	 * @param string $message Message à journaliser (sans préfixe).
	 * @return void
	 */
	private function log( $message ) {
		$line = '[MP Agenda] ' . $message;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
		$this->debug_log[] = $line;
	}

	/**
	 * Retourne le journal de diagnostic accumulé lors du dernier sync_all()/sync_technician().
	 *
	 * @return array
	 */
	public function get_debug_log() {
		return $this->debug_log;
	}

	/**
	 * Enregistre les hooks (cron + admin-ajax OAuth).
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'mp_agenda_google_sync_cron', array( $this, 'sync_all' ) );
		add_action( 'mp_agenda_google_token_check_cron', array( $this, 'check_all_tokens' ) );
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

		// "Connecter l'agenda partagé" (mode 'shared') passe ?shared=1 au lieu d'un
		// technician_id : les tokens obtenus sont alors stockés dans les options WP
		// globales mp_agenda_shared_google_* au lieu d'une fiche technicien — voir
		// handle_callback().
		$is_shared     = ! empty( $_GET['shared'] );
		$technician_id = $is_shared ? 0 : ( isset( $_GET['technician_id'] ) ? absint( $_GET['technician_id'] ) : 0 );
		$client_id     = get_option( 'mp_agenda_google_client_id' );

		if ( ( ! $is_shared && ! $technician_id ) || ! $client_id ) {
			wp_die( esc_html__( 'Configuration Google incomplète.', 'mp-agenda' ) );
		}

		$oauth_key = $is_shared ? 'shared' : $technician_id;

		$state = wp_json_encode(
			array(
				'technician_id' => $technician_id,
				'shared'        => $is_shared,
				'nonce'         => wp_create_nonce( 'mp_agenda_google_oauth_' . $oauth_key ),
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

		if ( ! $code || ! is_array( $state ) || empty( $state['nonce'] ) || ( empty( $state['shared'] ) && empty( $state['technician_id'] ) ) ) {
			wp_die( esc_html__( 'Réponse Google invalide.', 'mp-agenda' ) );
		}

		$is_shared     = ! empty( $state['shared'] );
		$technician_id = absint( $state['technician_id'] ?? 0 );
		$oauth_key     = $is_shared ? 'shared' : $technician_id;

		if ( ! wp_verify_nonce( $state['nonce'], 'mp_agenda_google_oauth_' . $oauth_key ) ) {
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

		if ( $is_shared ) {
			// Agenda partagé : les tokens vont dans des options WP globales, pas sur
			// une fiche technicien — voir get_credentials_for()/save_credentials().
			update_option( 'mp_agenda_shared_google_access_token', $data['google_access_token'] );
			update_option( 'mp_agenda_shared_google_token_expires_at', $data['google_token_expires_at'] );
			update_option( 'mp_agenda_shared_google_calendar_id', $data['google_calendar_id'] );
			if ( isset( $data['google_refresh_token'] ) ) {
				update_option( 'mp_agenda_shared_google_refresh_token', $data['google_refresh_token'] );
			}

			$email = $this->fetch_google_account_email( $body['access_token'] );
			if ( $email ) {
				update_option( 'mp_agenda_shared_google_email', $email );
			}
		} else {
			MP_Agenda_DB::save_technician( $data, $technician_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-technicians', 'mp_agenda_notice' => 'connected' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Récupère l'adresse email du compte Google qui vient de se connecter, pour
	 * affichage "✅ Agenda partagé connecté à {email}" dans MP Agenda > Commerciaux.
	 * Best-effort : ne fait jamais échouer la connexion OAuth elle-même, retourne
	 * simplement une chaîne vide si le scope email n'a pas été accordé ou si la
	 * requête échoue.
	 *
	 * @param string $access_token Access token fraîchement obtenu.
	 * @return string
	 */
	private function fetch_google_account_email( $access_token ) {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['email'] ?? '';
	}

	/* ---------------------------------------------------------------------
	 * Mode de synchro (individuel / partagé) & identifiants
	 * ------------------------------------------------------------------- */

	/**
	 * Retourne le mode de synchro Google configuré dans Réglages → Google API.
	 *
	 * @return string 'individual' ou 'shared'.
	 */
	private function get_sync_mode() {
		$mode = get_option( 'mp_agenda_google_sync_mode', 'individual' );
		return 'shared' === $mode ? 'shared' : 'individual';
	}

	/**
	 * Point d'entrée UNIQUE pour lire des identifiants Google (tokens +
	 * calendar_id). Tout le reste du plugin doit passer par cette méthode —
	 * ensure_valid_token(), push_appointment(), delete_event(), get_freebusy(),
	 * sync_technician() — au lieu de lire google_access_token/google_refresh_token
	 * directement sur un technicien, pour rester correct dans les deux modes :
	 * - 'individual' (défaut, historique) : les colonnes du technicien passé en
	 *   paramètre.
	 * - 'shared' : les options WP globales de l'agenda partagé, identiques pour
	 *   tous les commerciaux — $technician ne sert alors qu'au rattachement des
	 *   blocked_slots créés et aux logs, pas aux identifiants eux-mêmes.
	 *
	 * Le tableau retourné a toujours la forme des colonnes technicien, pour que
	 * le reste du code n'ait pas à savoir d'où viennent les identifiants.
	 * Pendant en écriture : save_credentials().
	 *
	 * @param array $technician Données du technicien.
	 * @return array{google_access_token:?string,google_refresh_token:?string,google_token_expires_at:?string,google_calendar_id:?string}
	 */
	private function get_credentials_for( $technician ) {
		if ( 'shared' === $this->get_sync_mode() ) {
			return array(
				'google_access_token'     => get_option( 'mp_agenda_shared_google_access_token' ) ?: null,
				'google_refresh_token'    => get_option( 'mp_agenda_shared_google_refresh_token' ) ?: null,
				'google_token_expires_at' => get_option( 'mp_agenda_shared_google_token_expires_at' ) ?: null,
				'google_calendar_id'      => get_option( 'mp_agenda_shared_google_calendar_id' ) ?: null,
			);
		}

		return array(
			'google_access_token'     => $technician['google_access_token'] ?? null,
			'google_refresh_token'    => $technician['google_refresh_token'] ?? null,
			'google_token_expires_at' => $technician['google_token_expires_at'] ?? null,
			'google_calendar_id'      => $technician['google_calendar_id'] ?? null,
		);
	}

	/**
	 * Enregistre des identifiants Google mis à jour à l'endroit approprié selon
	 * le mode de synchro — pendant en écriture de get_credentials_for().
	 *
	 * @param array $technician Technicien concerné (ignoré en mode 'shared').
	 * @param array $data       Sous-ensemble de google_access_token / google_refresh_token /
	 *                          google_token_expires_at / google_calendar_id à mettre à jour
	 *                          (une valeur null supprime l'option / vide la colonne).
	 * @return void
	 */
	private function save_credentials( $technician, $data ) {
		if ( 'shared' !== $this->get_sync_mode() ) {
			MP_Agenda_DB::save_technician( $data, $technician['id'] );
			return;
		}

		$option_map = array(
			'google_access_token'     => 'mp_agenda_shared_google_access_token',
			'google_refresh_token'    => 'mp_agenda_shared_google_refresh_token',
			'google_token_expires_at' => 'mp_agenda_shared_google_token_expires_at',
			'google_calendar_id'      => 'mp_agenda_shared_google_calendar_id',
		);

		foreach ( $option_map as $field => $option_name ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			if ( null === $data[ $field ] ) {
				delete_option( $option_name );
			} else {
				update_option( $option_name, $data[ $field ] );
			}
		}
	}

	/**
	 * Indique si des identifiants Google exploitables (individuels ou
	 * partagés, selon le mode configuré) sont disponibles pour ce technicien.
	 * À utiliser par le reste du plugin (ex. MP_Agenda_REST_API) à la place
	 * d'une lecture directe de google_refresh_token, qui ignorerait le mode
	 * partagé.
	 *
	 * @param array $technician Données du technicien.
	 * @return bool
	 */
	public function has_google_connected( $technician ) {
		$credentials = $this->get_credentials_for( $technician );
		return ! empty( $credentials['google_refresh_token'] );
	}

	/**
	 * Vérifie et renouvelle si besoin l'access_token à utiliser pour ce
	 * technicien (identifiants individuels ou partagés selon get_credentials_for()),
	 * à appeler AVANT tout appel à l'API Google (events.list,
	 * events.insert/patch/delete, freeBusy, etc.) — aucun appel Google ne doit
	 * plus lire google_access_token directement.
	 *
	 * Renouvelle avec une marge de 5 minutes (pas seulement une fois le token
	 * expiré) pour ne jamais démarrer un appel avec un token sur le point
	 * d'expirer en cours de route. En cas d'échec réseau temporaire, réessaie
	 * jusqu'à 2 fois avec 2 secondes d'attente. Si Google rejette carrément le
	 * refresh_token (error=invalid_grant, ex. accès révoqué par l'utilisateur ou
	 * expiration du mode "Testing" au bout de 7 jours), les identifiants sont
	 * marqués déconnectés (technicien ou agenda partagé) et l'admin WordPress
	 * est alerté par email — voir disconnect_and_alert().
	 *
	 * @param array $technician Données du technicien (doit contenir au moins 'id').
	 * @return string|false Access token déchiffré valide, ou false si impossible à obtenir.
	 */
	private function ensure_valid_token( $technician ) {
		$credentials = $this->get_credentials_for( $technician );

		if ( empty( $credentials['google_refresh_token'] ) ) {
			return false;
		}

		$expires_at = $credentials['google_token_expires_at'] ? strtotime( $credentials['google_token_expires_at'] ) : 0;

		if ( $expires_at > time() + 5 * MINUTE_IN_SECONDS && ! empty( $credentials['google_access_token'] ) ) {
			return $this->decrypt( $credentials['google_access_token'] );
		}

		$max_attempts = 3; // 1 tentative initiale + 2 réessais.
		$mode         = $this->get_sync_mode();

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$result = $this->do_refresh_access_token( $technician, $credentials );

			if ( 'invalid_grant' === ( $result['error'] ?? '' ) ) {
				$this->log( sprintf( 'ensure_valid_token tech_id=%d (mode=%s) : refresh_token rejeté par Google (invalid_grant) — déconnexion et alerte admin.', $technician['id'] ?? 0, $mode ) );
				$this->disconnect_and_alert( $technician );
				return false;
			}

			if ( ! empty( $result['access_token'] ) ) {
				$this->log( sprintf( 'ensure_valid_token tech_id=%d (mode=%s) : access_token renouvelé (tentative %d/%d).', $technician['id'] ?? 0, $mode, $attempt, $max_attempts ) );
				return $result['access_token'];
			}

			$this->log( sprintf(
				'ensure_valid_token tech_id=%d (mode=%s) : échec du renouvellement (tentative %d/%d) — %s',
				$technician['id'] ?? 0,
				$mode,
				$attempt,
				$max_attempts,
				$result['message'] ?? 'raison inconnue'
			) );

			if ( $attempt < $max_attempts ) {
				sleep( 2 );
			}
		}

		$this->log( sprintf( 'ensure_valid_token tech_id=%d (mode=%s) : échec définitif après %d tentatives (probable panne réseau temporaire côté Google).', $technician['id'] ?? 0, $mode, $max_attempts ) );
		return false;
	}

	/**
	 * Exécute une tentative de renouvellement d'access_token auprès de Google.
	 * En cas de succès, enregistre immédiatement le nouvel access_token,
	 * expires_at, et — si Google en a renvoyé un — le nouveau refresh_token, à
	 * l'endroit approprié selon le mode (via save_credentials()).
	 *
	 * @param array $technician  Données du technicien.
	 * @param array $credentials Identifiants courants (voir get_credentials_for()).
	 * @return array{access_token?:string,error?:string,message?:string}
	 */
	private function do_refresh_access_token( $technician, $credentials ) {
		$refresh_token = $this->decrypt( $credentials['google_refresh_token'] );

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
			return array( 'message' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['error'] ) ) {
			return array(
				'error'   => $body['error'],
				'message' => $body['error_description'] ?? $body['error'],
			);
		}

		if ( empty( $body['access_token'] ) ) {
			return array( 'message' => sprintf( 'réponse Google inattendue (HTTP %d)', $code ) );
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) ( $body['expires_in'] ?? 3600 ) );

		$update = array(
			'google_access_token'     => $this->encrypt( $body['access_token'] ),
			'google_token_expires_at' => $expires_at,
		);

		// Google peut renvoyer un nouveau refresh_token lors du renouvellement
		// (rare mais documenté) : on le stocke alors à la place de l'ancien.
		if ( ! empty( $body['refresh_token'] ) ) {
			$update['google_refresh_token'] = $this->encrypt( $body['refresh_token'] );
		}

		$this->save_credentials( $technician, $update );

		return array( 'access_token' => $body['access_token'] );
	}

	/**
	 * Déconnecte les identifiants Google en cours d'usage — technicien (mode
	 * individuel) ou agenda partagé (mode 'shared'), via save_credentials() —
	 * et alerte l'admin WordPress par email pour qu'il reconnecte manuellement
	 * depuis MP Agenda → Commerciaux.
	 *
	 * @param array $technician Données du technicien concerné (mode individuel)
	 *                          ou premier commercial actif (mode partagé, pour
	 *                          les logs uniquement).
	 * @return void
	 */
	private function disconnect_and_alert( $technician ) {
		$mode = $this->get_sync_mode();

		$this->save_credentials(
			$technician,
			array(
				'google_calendar_id'      => null,
				'google_access_token'     => null,
				'google_refresh_token'    => null,
				'google_token_expires_at' => null,
			)
		);

		if ( 'shared' === $mode ) {
			delete_option( 'mp_agenda_shared_google_email' );
		}

		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}

		if ( 'shared' === $mode ) {
			$subject = __( '[MP Agenda] L\'agenda Google partagé a été déconnecté', 'mp-agenda' );
			$message = __( '⚠️ L\'agenda Google partagé a été déconnecté (accès révoqué ou expiré côté Google). Reconnectez-le dans MP Agenda → Commerciaux.', 'mp-agenda' );
		} else {
			$name    = $technician['name'] ?? ( '#' . $technician['id'] );
			$subject = __( '[MP Agenda] Un commercial a été déconnecté de Google Agenda', 'mp-agenda' );
			$message = sprintf(
				/* translators: %s: nom du commercial */
				__( '⚠️ Le commercial %s a été déconnecté de Google Agenda. Reconnectez-le dans MP Agenda → Commerciaux.', 'mp-agenda' ),
				$name
			);
		}

		wp_mail( $admin_email, $subject, $message );

		$this->log( sprintf( 'disconnect_and_alert (mode=%s) : tokens vidés, email d\'alerte envoyé à %s.', $mode, $admin_email ) );
	}

	/**
	 * Vérifie quotidiennement le(s) token(s) Google en cours d'usage
	 * (renouvellement si besoin), pour détecter proactivement une déconnexion
	 * plutôt que d'attendre le prochain RDV/synchro :
	 * - mode 'individual' : chaque technicien connecté individuellement.
	 * - mode 'shared' : une seule vérification de l'agenda partagé.
	 * L'alerte admin en cas de token définitivement irrécupérable est déjà
	 * envoyée par ensure_valid_token()/disconnect_and_alert() — inutile de la
	 * dupliquer ici.
	 *
	 * Appelée par le cron quotidien mp_agenda_google_token_check_cron
	 * (voir MP_Agenda_Activator::schedule_cron() / MP_Agenda_Deactivator).
	 *
	 * @return void
	 */
	public function check_all_tokens() {
		$technicians = MP_Agenda_DB::get_technicians( true );

		if ( empty( $technicians ) ) {
			return;
		}

		if ( 'shared' === $this->get_sync_mode() ) {
			if ( ! $this->has_google_connected( $technicians[0] ) ) {
				return;
			}

			$this->log( 'check_all_tokens : vérification de l\'agenda Google partagé.' );
			$access_token = $this->ensure_valid_token( $technicians[0] );

			if ( ! $access_token ) {
				$this->log( 'check_all_tokens : agenda Google partagé irrécupérable pour l\'instant.' );
			}
			return;
		}

		foreach ( $technicians as $technician ) {
			if ( empty( $technician['google_refresh_token'] ) ) {
				continue;
			}

			$this->log( sprintf( 'check_all_tokens : vérification tech_id=%d (%s)', $technician['id'], $technician['name'] ?? '?' ) );

			$access_token = $this->ensure_valid_token( $technician );

			if ( ! $access_token ) {
				$this->log( sprintf( 'check_all_tokens : tech_id=%d irrécupérable pour l\'instant.', $technician['id'] ) );
			}
		}
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

		if ( ! $technician ) {
			return;
		}

		// En mode 'shared', les identifiants viennent de l'agenda partagé (pas du
		// technicien assigné au RDV) : le RDV est donc créé dans l'agenda partagé,
		// pas dans un agenda individuel — voir get_credentials_for().
		$credentials = $this->get_credentials_for( $technician );
		if ( empty( $credentials['google_refresh_token'] ) ) {
			return;
		}

		$access_token = $this->ensure_valid_token( $technician );
		if ( ! $access_token ) {
			return;
		}

		$calendar_id = $credentials['google_calendar_id'] ?: 'primary';

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

		if ( ! $technician || empty( $appointment['google_event_id'] ) ) {
			return;
		}

		$credentials = $this->get_credentials_for( $technician );
		if ( empty( $credentials['google_refresh_token'] ) ) {
			return;
		}

		$access_token = $this->ensure_valid_token( $technician );
		if ( ! $access_token ) {
			return;
		}

		$calendar_id = $credentials['google_calendar_id'] ?: 'primary';

		wp_remote_request(
			self::API_BASE . "/calendars/{$calendar_id}/events/{$appointment['google_event_id']}",
			array(
				'method'  => 'DELETE',
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * FreeBusy (vérification temps réel des disponibilités)
	 * ------------------------------------------------------------------- */

	/**
	 * Interroge l'API FreeBusy de Google pour les périodes occupées du
	 * calendrier d'un technicien sur une journée donnée — en complément des RDV
	 * et créneaux bloqués déjà en BDD, pour repérer un événement Google très
	 * récent que la synchro périodique n'a pas encore récupéré.
	 *
	 * Repli TOUJOURS silencieux : au moindre problème (pas de Google connecté,
	 * token, réseau, réponse inattendue), retourne un tableau vide plutôt que de
	 * faire échouer l'appelant. Le formulaire client ne doit JAMAIS être bloqué
	 * par un souci côté Google — voir get_available_slots()/book_appointment()
	 * dans MP_Agenda_REST_API, qui retombent alors sur les données BDD seules.
	 *
	 * Le résultat est mis en cache 2 minutes (transient) par technicien/date
	 * pour éviter d'appeler Google à chaque frappe/rafraîchissement du
	 * formulaire de réservation.
	 *
	 * @param array  $technician Données du technicien.
	 * @param string $date       Date au format Y-m-d.
	 * @return array Liste de périodes occupées : array( array( 'start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s' ), ... ).
	 */
	public function get_freebusy( $technician, $date ) {
		$mode    = $this->get_sync_mode();
		$tech_id = $technician['id'] ?? 0;

		// En mode partagé, tous les commerciaux consultent le MÊME calendrier
		// Google : un cache par technicien serait à la fois redondant (un appel
		// Google par commercial pour la même info) et potentiellement incohérent
		// (chacun avec sa propre fenêtre de cache) — un seul cache partagé suffit.
		$cache_key = 'shared' === $mode ? 'mp_agenda_freebusy_shared_' . $date : 'mp_agenda_freebusy_' . $tech_id . '_' . $date;

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			$this->log( sprintf( 'get_freebusy tech_id=%d (mode=%s), date=%s : réponse depuis le cache (transient).', $tech_id, $mode, $date ) );
			return $cached;
		}

		$credentials = $this->get_credentials_for( $technician );
		if ( empty( $credentials['google_refresh_token'] ) ) {
			return array();
		}

		$access_token = $this->ensure_valid_token( $technician );
		if ( ! $access_token ) {
			$this->log( sprintf( 'get_freebusy tech_id=%d (mode=%s), date=%s : pas d\'access_token valide — fallback silencieux vers [].', $tech_id, $mode, $date ) );
			return array();
		}

		$calendar_id = $credentials['google_calendar_id'] ?: 'primary';

		try {
			$time_min_dt = new DateTime( $date . ' 00:00:00', wp_timezone() );
		} catch ( \Exception $e ) {
			$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : date invalide — fallback silencieux vers [].', $tech_id, $date ) );
			return array();
		}
		$time_max_dt = clone $time_min_dt;
		$time_max_dt->modify( '+1 day' );

		// Format RFC3339 avec l'offset local du site (ex. "2026-09-16T00:00:00+02:00"),
		// comme le fait déjà to_rfc3339() pour push_appointment().
		$request_body = array(
			'timeMin' => $time_min_dt->format( 'Y-m-d\TH:i:sP' ),
			'timeMax' => $time_max_dt->format( 'Y-m-d\TH:i:sP' ),
			'items'   => array( array( 'id' => $calendar_id ) ),
		);

		$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : POST /freeBusy body=%s', $tech_id, $date, wp_json_encode( $request_body ) ) );

		$response = wp_remote_post(
			self::API_BASE . '/freeBusy',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : erreur requête Google — %s — fallback silencieux vers [].', $tech_id, $date, $response->get_error_message() ) );
			return array();
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : HTTP %d — %s', $tech_id, $date, $code, substr( $raw_body, 0, 300 ) ) );

		if ( $code >= 400 ) {
			return array();
		}

		$decoded  = json_decode( $raw_body, true );
		$busy_raw = $decoded['calendars'][ $calendar_id ]['busy'] ?? null;

		if ( ! is_array( $busy_raw ) ) {
			$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : réponse Google inexploitable — fallback silencieux vers [].', $tech_id, $date ) );
			return array();
		}

		$busy_periods = array();
		foreach ( $busy_raw as $period ) {
			if ( empty( $period['start'] ) || empty( $period['end'] ) ) {
				continue;
			}
			try {
				$busy_periods[] = array(
					'start' => ( new DateTime( $period['start'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ),
					'end'   => ( new DateTime( $period['end'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ),
				);
			} catch ( \Exception $e ) {
				continue;
			}
		}

		$this->log( sprintf( 'get_freebusy tech_id=%d, date=%s : %d période(s) busy retournée(s) par Google.', $tech_id, $date, count( $busy_periods ) ) );

		set_transient( $cache_key, $busy_periods, 2 * MINUTE_IN_SECONDS );

		return $busy_periods;
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
	 * @param bool $full_sync Si true (synchro manuelle via le bouton), ignore le
	 *                        timestamp de dernière synchro et récupère tous les
	 *                        événements des 30 derniers jours plutôt que seulement
	 *                        ceux modifiés depuis le dernier passage. Le cron
	 *                        (mp_agenda_google_sync_cron) doit laisser ce paramètre
	 *                        à false pour rester incrémental.
	 * @return void
	 */
	public function sync_all( $full_sync = false ) {
		if ( 'shared' === $this->get_sync_mode() ) {
			$this->sync_shared_calendar( $full_sync );
			return;
		}

		$technicians = MP_Agenda_DB::get_technicians( true );

		foreach ( $technicians as $technician ) {
			if ( empty( $technician['google_refresh_token'] ) ) {
				continue;
			}
			$this->sync_technician( $technician, $full_sync );
		}
	}

	/**
	 * Synchronise l'agenda Google partagé (mode 'shared') : UNE SEULE requête
	 * events.list avec les identifiants globaux, au lieu d'une synchro par
	 * commercial. Les événements Google non reconnus sont créés comme
	 * blocked_slots rattachés au premier commercial actif — la table
	 * blocked_slots impose un technician_id (colonne NOT NULL), il n'est donc
	 * pas possible de créer un créneau bloqué "sans commercial" sans modifier
	 * le schéma de la table.
	 *
	 * @param bool $full_sync Voir sync_technician().
	 * @return void
	 */
	private function sync_shared_calendar( $full_sync = false ) {
		$technicians = MP_Agenda_DB::get_technicians( true );

		if ( empty( $technicians ) ) {
			$this->log( 'sync_shared_calendar : aucun commercial actif — synchro annulée (blocked_slots nécessite un technician_id).' );
			return;
		}

		if ( ! $this->has_google_connected( $technicians[0] ) ) {
			$this->log( 'sync_shared_calendar : agenda Google partagé non connecté — synchro annulée.' );
			return;
		}

		$this->log( sprintf(
			'sync_shared_calendar : synchro de l\'agenda partagé, créneaux bloqués rattachés au premier commercial actif (tech_id=%d, %s).',
			$technicians[0]['id'],
			$technicians[0]['name'] ?? '?'
		) );

		// sync_technician() lit déjà les identifiants via get_credentials_for() :
		// en mode partagé, tous les technicians partagent le même appel Google —
		// seul le premier commercial actif sert de rattachement pour les
		// blocked_slots créés et pour le curseur de synchro (option_key ci-dessous).
		$this->sync_technician( $technicians[0], $full_sync );
	}

	/**
	 * Synchronise le calendrier Google (individuel du technicien, ou partagé
	 * selon le mode configuré — voir get_credentials_for()) vers les tables locales.
	 *
	 * @param array $technician Données du technicien.
	 * @param bool  $full_sync  Si true (synchro manuelle "🔄 Synchroniser Google"),
	 *                          repart entièrement à zéro : le timestamp/syncToken
	 *                          stocké est supprimé et la requête n'utilise jamais
	 *                          updatedMin (qui renvoie une erreur 410
	 *                          "updatedMinTooLongAgo" passé un certain délai), mais
	 *                          une fenêtre temporelle explicite timeMin/timeMax.
	 *                          Si false (cron), la requête reste incrémentale via
	 *                          updatedMin, avec repli automatique sur la même
	 *                          fenêtre temporelle en cas de 410.
	 * @return void
	 */
	private function sync_technician( $technician, $full_sync = false ) {
		$mode        = $this->get_sync_mode();
		$credentials = $this->get_credentials_for( $technician );

		$this->log( sprintf(
			'sync_technician START for tech_id=%d, name=%s, mode=%s, calendar_id=%s, full_sync=%s',
			$technician['id'],
			$technician['name'] ?? '?',
			$mode,
			$credentials['google_calendar_id'] ?: '(vide, repli sur "primary")',
			$full_sync ? 'true' : 'false'
		) );

		$access_token = $this->ensure_valid_token( $technician );
		if ( ! $access_token ) {
			$this->log( sprintf( 'sync_technician tech_id=%d : impossible d\'obtenir un access_token valide (refresh_token absent/invalide) — synchro annulée.', $technician['id'] ) );
			return;
		}

		$calendar_id = $credentials['google_calendar_id'] ?: 'primary';
		// En mode partagé, le curseur de dernière synchro est global (un seul
		// calendrier pour tout le monde) plutôt que rattaché au technicien qui a
		// servi de point d'entrée, qui peut changer d'une synchro à l'autre.
		$option_key = 'shared' === $mode ? 'mp_agenda_google_last_sync_shared' : 'mp_agenda_google_last_sync_' . $technician['id'];
		$now        = gmdate( 'Y-m-d\TH:i:s\Z' );

		if ( $full_sync ) {
			// Synchro manuelle : on ignore délibérément tout état de synchro
			// précédent. updatedMin n'est jamais envoyé ici — Google y répond par
			// une erreur 410 "updatedMinTooLongAgo" au-delà d'un certain délai —
			// on interroge donc une fenêtre temporelle explicite à la place.
			delete_option( $option_key );
			$this->log( sprintf( 'sync_technician tech_id=%d : full_sync=true — syncToken/timestamp stocké supprimé, requête sans updatedMin (timeMin/timeMax).', $technician['id'] ) );
			$params = $this->build_time_window_params();
		} else {
			$last_sync   = get_option( $option_key );
			$updated_min = $last_sync ? $last_sync : gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) );
			$params      = array(
				'singleEvents' => 'true',
				'updatedMin'   => $updated_min,
				'showDeleted'  => 'true',
				'maxResults'   => 250,
			);
		}

		$result = $this->request_google_events( $calendar_id, $access_token, $params );

		if ( isset( $result['error'] ) ) {
			$this->log( sprintf( 'sync_technician tech_id=%d : erreur requête Google — %s', $technician['id'], $result['error']->get_error_message() ) );
			return;
		}

		if ( ! $full_sync && 410 === $result['code'] ) {
			// Repli recommandé par Google : un 410 Gone sur updatedMin signifie
			// qu'il est trop ancien/invalide pour une synchro incrémentale. On
			// oublie le timestamp stocké et on repart sur une fenêtre temporelle,
			// exactement comme en full_sync.
			$this->log( sprintf( 'sync_technician tech_id=%d : Google a renvoyé 410 (updatedMinTooLongAgo) — suppression du syncToken/timestamp stocké et nouvelle requête sans updatedMin.', $technician['id'] ) );
			delete_option( $option_key );
			$params = $this->build_time_window_params();
			$result = $this->request_google_events( $calendar_id, $access_token, $params );

			if ( isset( $result['error'] ) ) {
				$this->log( sprintf( 'sync_technician tech_id=%d : erreur requête Google (après repli 410) — %s', $technician['id'], $result['error']->get_error_message() ) );
				return;
			}
		}

		$code     = $result['code'];
		$raw_body = $result['raw_body'];

		if ( $code >= 400 ) {
			$this->log( sprintf( 'sync_technician tech_id=%d : réponse d\'erreur HTTP %d de Google, synchro annulée pour ce technicien.', $technician['id'], $code ) );
			return;
		}

		$body = json_decode( $raw_body, true );

		if ( empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
			$this->log( sprintf( 'Events found: 0 (calendar_id=%s, params=%s)', $calendar_id, wp_json_encode( $params ) ) );
			update_option( $option_key, $now );
			return;
		}

		$this->log( 'Events found: ' . count( $body['items'] ) );

		foreach ( $body['items'] as $event ) {
			$event_id = $event['id'] ?? '?';
			$this->log( sprintf(
				'Processing event: id=%s, summary=%s, start=%s, end=%s, status=%s',
				$event_id,
				$event['summary'] ?? '(sans titre)',
				$event['start']['dateTime'] ?? ( $event['start']['date'] ?? '?' ),
				$event['end']['dateTime'] ?? ( $event['end']['date'] ?? '?' ),
				$event['status'] ?? '?'
			) );

			$this->log( sprintf( 'reconcile_event called for event %s', $event_id ) );
			$result = $this->reconcile_event( $technician, $event );
			$this->log( sprintf( 'reconcile_event result: %s', $result ) );
		}

		update_option( $option_key, $now );
	}

	/**
	 * Construit les paramètres de requête events.list sans updatedMin, filtrés sur
	 * une fenêtre temporelle explicite (30 jours dans le passé, 60 jours dans le
	 * futur). Utilisé en synchro manuelle (full_sync) et en repli après une
	 * erreur 410 "updatedMinTooLongAgo" côté cron.
	 *
	 * @return array
	 */
	private function build_time_window_params() {
		return array(
			'singleEvents' => 'true',
			'timeMin'      => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) ),
			'timeMax'      => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+60 days' ) ),
			'showDeleted'  => 'true',
			'maxResults'   => 250,
		);
	}

	/**
	 * Exécute la requête GET events.list vers l'API Google Calendar et journalise
	 * l'URL appelée ainsi que le code/corps de la réponse à des fins de diagnostic.
	 *
	 * @param string $calendar_id  ID du calendrier Google.
	 * @param string $access_token Jeton d'accès valide.
	 * @param array  $params       Paramètres de requête (updatedMin OU timeMin/timeMax, etc.).
	 * @return array Soit array( 'error' => WP_Error ), soit array( 'code' => int, 'raw_body' => string ).
	 */
	private function request_google_events( $calendar_id, $access_token, $params ) {
		$url = self::API_BASE . "/calendars/{$calendar_id}/events?" . http_build_query( $params );
		$this->log( 'Google API URL: ' . $url );

		$response = wp_remote_get(
			$url,
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) )
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		$this->log( 'Google API response code: ' . $code );
		$this->log( 'Google API response body: ' . substr( $raw_body, 0, 500 ) );

		return array(
			'code'     => $code,
			'raw_body' => $raw_body,
		);
	}

	/**
	 * Réconcilie un événement Google avec les tables locales (RDV ou créneaux bloqués).
	 *
	 * @param array $technician Données du technicien.
	 * @param array $event      Événement Google Agenda.
	 * @return string Description du résultat (à des fins de diagnostic uniquement).
	 */
	private function reconcile_event( $technician, $event ) {
		global $wpdb;

		if ( empty( $event['id'] ) ) {
			return 'skipped: événement sans id';
		}

		$event_id = $event['id'];

		// Événement supprimé côté Google : on supprime le RDV ou le créneau bloqué local.
		if ( 'cancelled' === ( $event['status'] ?? '' ) ) {
			$appointment = MP_Agenda_DB::get_appointment_by_google_id( $event_id );
			$this->log( sprintf( 'Event %s: has matching appointment google_event_id? %s', $event_id, $appointment ? 'YES' : 'NO' ) );
			if ( $appointment ) {
				MP_Agenda_DB::save_appointment( array( 'status' => 'cancelled' ), $appointment['id'] );
				return 'skipped: appointment id=' . $appointment['id'] . ' marqué cancelled (événement Google annulé)';
			}
			MP_Agenda_DB::delete_blocked_slot_by_google_id( $event_id );
			return 'skipped: blocked_slot supprimé (événement Google annulé)';
		}

		if ( ! empty( $event['start']['dateTime'] ) && ! empty( $event['end']['dateTime'] ) ) {
			// Événement horodaté classique.
			// Les dates stockées en base (RDV et créneaux bloqués) sont toujours en
			// heure locale du site (voir to_rfc3339(), qui fait l'inverse avec
			// wp_timezone()). gmdate( 'Y-m-d H:i:s', strtotime( ... ) ) convertirait
			// ici en UTC, ce qui décale l'événement importé de Google (ex. +1h/+2h
			// en France) et peut le faire apparaître sur un autre jour, voire hors
			// des horaires de travail — il semble alors "absent" du planning alors
			// qu'il a bien été synchronisé.
			$start = ( new DateTime( $event['start']['dateTime'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
			$end   = ( new DateTime( $event['end']['dateTime'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
			$this->log( sprintf( 'Event %s: événement horodaté — start=%s, end=%s', $event_id, $start, $end ) );
		} elseif ( ! empty( $event['start']['date'] ) && ! empty( $event['end']['date'] ) ) {
			// Événement "toute la journée" : start.date/end.date sont de simples
			// dates calendaires (YYYY-MM-DD), sans heure ni fuseau à convertir. Le
			// end.date de Google est EXCLUSIF (un événement du 16/09 a
			// end.date=17/09) : en le mappant tel quel sur "17/09 00:00:00", le
			// créneau bloqué couvre exactement 16/09 00:00 -> 17/09 00:00, soit
			// bien toute la journée du 16/09 (get_busy_slots()/is_slot_available()
			// bloquent alors tous les créneaux de travail de cette journée).
			$start = $event['start']['date'] . ' 00:00:00';
			$end   = $event['end']['date'] . ' 00:00:00';
			$this->log( sprintf( 'Event %s: événement toute la journée — start=%s, end=%s', $event_id, $start, $end ) );
		} else {
			// Ni dateTime ni date exploitables : rien à synchroniser.
			return 'skipped: événement sans start/end exploitable (ni dateTime ni date)';
		}

		// Un événement déjà lié à un RDV créé depuis le plugin : on met juste à jour les horaires.
		$appointment = MP_Agenda_DB::get_appointment_by_google_id( $event_id );
		$this->log( sprintf( 'Event %s: has matching appointment google_event_id? %s', $event_id, $appointment ? 'YES' : 'NO' ) );
		if ( $appointment ) {
			MP_Agenda_DB::save_appointment(
				array(
					'start_datetime' => $start,
					'end_datetime'   => $end,
				),
				$appointment['id']
			);
			return 'updated appointment id=' . $appointment['id'];
		}

		// Événement créé/modifié directement dans Google Agenda : on bloque simplement le créneau
		// (pas assez d'informations client pour créer un rendez-vous complet).
		$blocked = MP_Agenda_DB::get_blocked_slot_by_google_id( $event_id );
		$this->log( sprintf( 'Event %s: has matching blocked_slot google_event_id? %s', $event_id, $blocked ? 'YES' : 'NO' ) );

		$data = array(
			'technician_id'   => $technician['id'],
			'start_datetime'  => $start,
			'end_datetime'    => $end,
			'reason'          => sanitize_text_field( $event['summary'] ?? __( 'Google Agenda', 'mp-agenda' ) ),
			'google_event_id' => $event_id,
		);

		if ( $blocked ) {
			MP_Agenda_DB::update_blocked_slot_by_google_id( $event_id, $data );
			$outcome = ( '' === $wpdb->last_error ) ? ( 'OK id=' . $blocked['id'] ) : ( 'FAIL: ' . $wpdb->last_error );
			$this->log( sprintf( 'Event %s: $wpdb->update result: %s', $event_id, $outcome ) );
			return 'updated blocked_slot id=' . $blocked['id'];
		}

		$this->log( sprintf( 'Event %s: creating new blocked_slot', $event_id ) );
		$new_id  = MP_Agenda_DB::create_blocked_slot( $data );
		$outcome = ( $new_id && '' === $wpdb->last_error ) ? ( 'OK id=' . $new_id ) : ( 'FAIL: ' . $wpdb->last_error );
		$this->log( sprintf( 'Event %s: $wpdb->insert result: %s', $event_id, $outcome ) );

		if ( ! $new_id ) {
			return 'skipped: échec création blocked_slot — ' . $wpdb->last_error;
		}

		return 'created blocked_slot id=' . $new_id;
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
