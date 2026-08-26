<?php
/**
 * Enregistre et gère les endpoints REST de MP Agenda.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_REST_API.
 */
class MP_Agenda_REST_API {

	/**
	 * Namespace des routes REST.
	 *
	 * @var string
	 */
	private $namespace = 'mp-agenda/v1';

	/**
	 * Enregistre les hooks REST.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Déclare toutes les routes de l'API.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/appointments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_appointments' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_appointment' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_appointment' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_appointment' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_appointment' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/blocked-slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_blocked_slots' ),
				'permission_callback' => array( $this, 'admin_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/debug',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_debug_info' ),
				'permission_callback' => array( $this, 'admin_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/technicians',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_technicians' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/showrooms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_showrooms' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_services' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/technicians/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_technician' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/available-slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_available_slots' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/book',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'book_appointment' ),
				'permission_callback' => array( $this, 'public_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/google/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'force_google_sync' ),
				'permission_callback' => array( $this, 'admin_permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/google/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect_google' ),
				'permission_callback' => array( $this, 'admin_permission_callback' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------- */

	/**
	 * Autorise uniquement les administrateurs (authentification par cookie + nonce WP REST).
	 *
	 * @return bool
	 */
	public function admin_permission_callback() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Autorise les requêtes publiques avec un rate limit simple par IP.
	 *
	 * @return bool|WP_Error
	 */
	public function public_permission_callback() {
		$ip  = $this->get_client_ip();
		$key = 'mp_agenda_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 20 ) {
			return new WP_Error( 'mp_agenda_rate_limited', __( 'Trop de requêtes, veuillez réessayer dans une minute.', 'mp-agenda' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Récupère l'adresse IP du client (best-effort, sans faire confiance aux en-têtes non fiables).
	 *
	 * @return string
	 */
	private function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	/* ---------------------------------------------------------------------
	 * Rendez-vous (admin)
	 * ------------------------------------------------------------------- */

	/**
	 * Liste les rendez-vous filtrés.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function get_appointments( WP_REST_Request $request ) {
		$args = array(
			'from'          => sanitize_text_field( (string) $request->get_param( 'from' ) ),
			'to'            => sanitize_text_field( (string) $request->get_param( 'to' ) ),
			'technician_id' => absint( $request->get_param( 'technician_id' ) ?: $request->get_param( 'technician' ) ),
			'status'        => sanitize_key( (string) $request->get_param( 'status' ) ),
		);

		$items = MP_Agenda_DB::get_appointments( $args );

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/**
	 * Récupère un rendez-vous unique.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_appointment( WP_REST_Request $request ) {
		$appointment = MP_Agenda_DB::get_appointment( (int) $request->get_param( 'id' ) );

		if ( ! $appointment ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Rendez-vous introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $appointment, 200 );
	}

	/**
	 * Valide et normalise les données d'un rendez-vous envoyées par l'admin.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return array
	 */
	private function sanitize_appointment_input( WP_REST_Request $request ) {
		$duration    = absint( $request->get_param( 'duration' ) ) ?: 60;
		$start       = sanitize_text_field( (string) $request->get_param( 'start_datetime' ) );
		$service_id  = $request->get_param( 'service_id' );

		$start_dt = new DateTime( $start );
		$end_dt   = clone $start_dt;
		$end_dt->modify( '+' . $duration . ' minutes' );

		return array(
			'technician_id'      => absint( $request->get_param( 'technician_id' ) ),
			'client_name'        => sanitize_text_field( (string) $request->get_param( 'client_name' ) ),
			'client_email'       => sanitize_email( (string) $request->get_param( 'client_email' ) ),
			'client_phone'       => sanitize_text_field( (string) $request->get_param( 'client_phone' ) ),
			'client_address'     => sanitize_textarea_field( (string) $request->get_param( 'client_address' ) ),
			'intervention_type'  => sanitize_text_field( (string) $request->get_param( 'intervention_type' ) ),
			'service_id'         => ( null !== $service_id && '' !== $service_id ) ? absint( $service_id ) : null,
			'surface'            => sanitize_text_field( (string) $request->get_param( 'surface' ) ),
			'urgency'            => in_array( $request->get_param( 'urgency' ), array( 'normal', 'urgent' ), true ) ? $request->get_param( 'urgency' ) : 'normal',
			'notes'              => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
			'internal_notes'     => sanitize_textarea_field( (string) $request->get_param( 'internal_notes' ) ),
			'start_datetime'     => $start_dt->format( 'Y-m-d H:i:s' ),
			'end_datetime'       => $end_dt->format( 'Y-m-d H:i:s' ),
			'duration'           => $duration,
			'status'             => in_array( $request->get_param( 'status' ), array( 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ? $request->get_param( 'status' ) : 'confirmed',
			'source'             => in_array( $request->get_param( 'source' ), array( 'online', 'admin', 'google' ), true ) ? $request->get_param( 'source' ) : 'admin',
		);
	}

	/**
	 * Crée un rendez-vous depuis l'administration.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_appointment( WP_REST_Request $request ) {
		$data = $this->sanitize_appointment_input( $request );

		if ( empty( $data['technician_id'] ) || empty( $data['client_name'] ) || empty( $data['client_phone'] ) ) {
			return new WP_Error( 'mp_agenda_invalid', __( 'Champs obligatoires manquants.', 'mp-agenda' ), array( 'status' => 400 ) );
		}

		if ( ! MP_Agenda_DB::is_slot_available( $data['technician_id'], $data['start_datetime'], $data['end_datetime'] ) ) {
			return new WP_Error( 'mp_agenda_slot_taken', __( 'Ce créneau n\'est plus disponible.', 'mp-agenda' ), array( 'status' => 409 ) );
		}

		$id = MP_Agenda_DB::save_appointment( $data );
		$appointment = MP_Agenda_DB::get_appointment( $id );

		$google_sync = new MP_Agenda_Google_Sync();
		$google_sync->push_appointment( $appointment );

		$notifications = new MP_Agenda_Notifications();
		$notifications->send_appointment_notifications( MP_Agenda_DB::get_appointment( $id ) );

		return new WP_REST_Response( MP_Agenda_DB::get_appointment( $id ), 201 );
	}

	/**
	 * Met à jour un rendez-vous existant.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_appointment( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		$existing = MP_Agenda_DB::get_appointment( $id );

		if ( ! $existing ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Rendez-vous introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		$data = $this->sanitize_appointment_input( $request );

		if ( ! MP_Agenda_DB::is_slot_available( $data['technician_id'], $data['start_datetime'], $data['end_datetime'], $id ) ) {
			return new WP_Error( 'mp_agenda_slot_taken', __( 'Ce créneau n\'est plus disponible.', 'mp-agenda' ), array( 'status' => 409 ) );
		}

		$data['google_event_id'] = $existing['google_event_id'];
		MP_Agenda_DB::save_appointment( $data, $id );
		$appointment = MP_Agenda_DB::get_appointment( $id );

		$google_sync = new MP_Agenda_Google_Sync();
		$google_sync->push_appointment( $appointment );

		return new WP_REST_Response( $appointment, 200 );
	}

	/**
	 * Supprime un rendez-vous.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_appointment( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		$existing = MP_Agenda_DB::get_appointment( $id );

		if ( ! $existing ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Rendez-vous introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $existing['google_event_id'] ) ) {
			$google_sync = new MP_Agenda_Google_Sync();
			$google_sync->delete_event( $existing );
		}

		MP_Agenda_DB::delete_appointment( $id );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Liste les créneaux bloqués sur une plage de dates (utilisé par le planning admin).
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function get_blocked_slots( WP_REST_Request $request ) {
		global $wpdb;

		$from          = sanitize_text_field( (string) $request->get_param( 'from' ) );
		$to            = sanitize_text_field( (string) $request->get_param( 'to' ) );
		$technician_id = absint( $request->get_param( 'technician_id' ) );

		$table = MP_Agenda_DB::table_blocked_slots();
		$where  = array( '1=1' );
		$params = array();

		if ( $from ) {
			$where[]  = 'start_datetime <= %s';
			$params[] = $to ? $to . ' 23:59:59' : $from . ' 23:59:59';
		}
		if ( $to || $from ) {
			$where[]  = 'end_datetime >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $technician_id ) {
			$where[]  = 'technician_id = %d';
			$params[] = $technician_id;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where );
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/**
	 * Endpoint de diagnostic TEMPORAIRE : exécute get_appointments() et la logique
	 * de get_blocked_slots() directement, avec affichage complet des erreurs,
	 * pour identifier la cause exacte des erreurs 500 sur ces deux endpoints.
	 *
	 * À SUPPRIMER une fois le diagnostic terminé (route dans register_routes() et cette méthode).
	 *
	 * @return WP_REST_Response
	 */
	public function get_debug_info() {
		error_reporting( E_ALL ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_reporting
		ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Blacklisted

		global $wpdb;

		$result = array(
			'php_version'     => PHP_VERSION,
			'appointments'    => null,
			'blocked_slots'   => null,
			'wpdb_last_error' => '',
		);

		try {
			$result['appointments'] = MP_Agenda_DB::get_appointments(
				array(
					'from' => '2026-08-19',
					'to'   => '2026-08-19',
				)
			);
		} catch ( \Throwable $e ) {
			$result['appointments'] = $this->format_debug_exception( $e );
		}

		try {
			$from          = '2026-08-19';
			$to            = '2026-08-19';
			$technician_id = 0;

			$table  = MP_Agenda_DB::table_blocked_slots();
			$where  = array( '1=1' );
			$params = array();

			if ( $from ) {
				$where[]  = 'start_datetime <= %s';
				$params[] = $to ? $to . ' 23:59:59' : $from . ' 23:59:59';
			}
			if ( $to || $from ) {
				$where[]  = 'end_datetime >= %s';
				$params[] = $from . ' 00:00:00';
			}
			if ( $technician_id ) {
				$where[]  = 'technician_id = %d';
				$params[] = $technician_id;
			}

			$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where );
			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$result['blocked_slots'] = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} catch ( \Throwable $e ) {
			$result['blocked_slots'] = $this->format_debug_exception( $e );
		}

		$result['wpdb_last_error'] = $wpdb->last_error;

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Formate une exception/erreur pour l'endpoint de diagnostic.
	 *
	 * @param \Throwable $e Exception ou erreur capturée.
	 * @return array
	 */
	private function format_debug_exception( \Throwable $e ) {
		return array(
			'error'   => true,
			'class'   => get_class( $e ),
			'message' => $e->getMessage(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
			'trace'   => $e->getTraceAsString(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Techniciens (public)
	 * ------------------------------------------------------------------- */

	/**
	 * Liste les techniciens actifs (endpoint public, données limitées).
	 * Filtrable par showroom_id : renvoie alors les techniciens rattachés à ce
	 * showroom, plus ceux sans showroom_id (rattachés à tous les showrooms).
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function get_technicians( WP_REST_Request $request ) {
		$showroom_id = absint( $request->get_param( 'showroom_id' ) );

		if ( $showroom_id ) {
			$technicians = MP_Agenda_DB::get_technicians_by_showroom( $showroom_id, true );
		} else {
			$technicians = MP_Agenda_DB::get_technicians( true );
		}

		$public_data = array_map( array( $this, 'format_public_technician' ), $technicians );

		return new WP_REST_Response( array( 'items' => $public_data ), 200 );
	}

	/**
	 * Liste les showrooms actifs (endpoint public, pour l'étape 1 du formulaire de réservation).
	 *
	 * @return WP_REST_Response
	 */
	public function get_showrooms() {
		$showrooms = MP_Agenda_DB::get_showrooms( true );

		$public_data = array_map( array( $this, 'format_public_showroom' ), $showrooms );

		return new WP_REST_Response( array( 'items' => $public_data ), 200 );
	}

	/**
	 * Formate les données publiques d'un showroom.
	 *
	 * @param array $showroom Données brutes du showroom.
	 * @return array
	 */
	private function format_public_showroom( $showroom ) {
		return array(
			'id'        => (int) $showroom['id'],
			'name'      => $showroom['name'],
			'address'   => $showroom['address'],
			'phone'     => $showroom['phone'],
			'photo_url' => $showroom['photo_url'],
		);
	}

	/**
	 * Liste les services actifs (endpoint public, pour l'étape 2 du formulaire de réservation).
	 *
	 * @return WP_REST_Response
	 */
	public function get_services() {
		$services = MP_Agenda_DB::get_services( true );

		$public_data = array_map( array( $this, 'format_public_service' ), $services );

		return new WP_REST_Response( array( 'items' => $public_data ), 200 );
	}

	/**
	 * Formate les données publiques d'un service.
	 *
	 * @param array $service Données brutes du service.
	 * @return array
	 */
	private function format_public_service( $service ) {
		return array(
			'id'          => (int) $service['id'],
			'name'        => $service['name'],
			'description' => $service['description'],
			'icon'        => $service['icon'],
			'duration'    => (int) $service['duration'],
		);
	}

	/**
	 * Détail public d'un technicien.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_technician( WP_REST_Request $request ) {
		$tech = MP_Agenda_DB::get_technician( (int) $request->get_param( 'id' ) );

		if ( ! $tech || empty( $tech['is_active'] ) ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Technicien introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_public_technician( $tech ), 200 );
	}

	/**
	 * Formate les données publiques d'un technicien (sans les informations sensibles
	 * comme les tokens Google), en incluant les horaires de travail pour permettre
	 * au formulaire client de griser les jours non travaillés sans requête supplémentaire.
	 *
	 * @param array $tech Données brutes du technicien.
	 * @return array
	 */
	private function format_public_technician( $tech ) {
		$working_hours = json_decode( (string) $tech['working_hours'], true );

		return array(
			'id'            => (int) $tech['id'],
			'name'          => $tech['name'],
			'zone'          => $tech['zone'],
			'photo_url'     => $tech['photo_url'],
			'showroom_id'   => isset( $tech['showroom_id'] ) && null !== $tech['showroom_id'] ? (int) $tech['showroom_id'] : null,
			'working_hours' => is_array( $working_hours ) ? $working_hours : array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Créneaux disponibles & réservation (public)
	 * ------------------------------------------------------------------- */

	/**
	 * Calcule les créneaux disponibles pour un technicien à une date donnée.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_available_slots( WP_REST_Request $request ) {
		$technician_id = absint( $request->get_param( 'technician_id' ) );
		$date          = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$duration      = absint( $request->get_param( 'duration' ) ) ?: 60;

		if ( ! $technician_id || ! $date ) {
			return new WP_Error( 'mp_agenda_invalid', __( 'Paramètres manquants.', 'mp-agenda' ), array( 'status' => 400 ) );
		}

		$technician = MP_Agenda_DB::get_technician( $technician_id );
		if ( ! $technician || empty( $technician['is_active'] ) ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Technicien introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		$slots = $this->compute_available_slots( $technician, $date, $duration );

		return new WP_REST_Response( array( 'date' => $date, 'slots' => $slots ), 200 );
	}

	/**
	 * Calcule les créneaux libres d'un technicien pour une journée donnée
	 * en croisant les horaires de travail, les RDV et les créneaux bloqués.
	 *
	 * @param array  $technician Données du technicien.
	 * @param string $date       Date au format Y-m-d.
	 * @param int    $duration   Durée souhaitée en minutes.
	 * @return array Liste de créneaux au format H:i.
	 */
	private function compute_available_slots( $technician, $date, $duration ) {
		$working_hours = json_decode( (string) $technician['working_hours'], true );
		if ( ! is_array( $working_hours ) ) {
			return array();
		}

		$day_map  = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_obj ) {
			return array();
		}

		$day_key = $day_map[ (int) $date_obj->format( 'w' ) ];
		$day_config = $working_hours[ $day_key ] ?? null;

		if ( ! $day_config || empty( $day_config['active'] ) ) {
			return array();
		}

		// N'affiche pas de créneaux dans le passé.
		$now = new DateTime( 'now', wp_timezone() );

		$work_start = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $day_config['start'] );
		$work_end   = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $day_config['end'] );

		if ( ! $work_start || ! $work_end || $work_start >= $work_end ) {
			return array();
		}

		$busy = MP_Agenda_DB::get_busy_slots( $technician['id'], $date, $date );

		$slots = array();
		$cursor = clone $work_start;

		while ( true ) {
			$slot_end = clone $cursor;
			$slot_end->modify( '+' . $duration . ' minutes' );

			if ( $slot_end > $work_end ) {
				break;
			}

			$is_free = true;

			if ( $cursor < $now ) {
				$is_free = false;
			}

			foreach ( $busy as $busy_slot ) {
				$busy_start = new DateTime( $busy_slot['start_datetime'] );
				$busy_end   = new DateTime( $busy_slot['end_datetime'] );

				if ( $cursor < $busy_end && $slot_end > $busy_start ) {
					$is_free = false;
					break;
				}
			}

			if ( $is_free ) {
				$slots[] = $cursor->format( 'H:i' );
			}

			$cursor->modify( '+30 minutes' );
		}

		return $slots;
	}

	/**
	 * Traite la réservation d'un rendez-vous par un client depuis le formulaire public.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function book_appointment( WP_REST_Request $request ) {
		$technician_id = absint( $request->get_param( 'technician_id' ) );
		$date          = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$time          = sanitize_text_field( (string) $request->get_param( 'time' ) );
		$service_id    = absint( $request->get_param( 'service_id' ) );
		$service       = $service_id ? MP_Agenda_DB::get_service( $service_id ) : null;
		$duration      = absint( $request->get_param( 'duration' ) )
			?: ( $service ? (int) $service['duration'] : 0 )
			?: (int) ( get_option( 'mp_agenda_settings' )['default_duration'] ?? 60 );

		$client_name    = sanitize_text_field( (string) $request->get_param( 'client_name' ) );
		$client_phone   = sanitize_text_field( (string) $request->get_param( 'client_phone' ) );
		$client_email   = sanitize_email( (string) $request->get_param( 'client_email' ) );
		$client_address = sanitize_textarea_field( (string) $request->get_param( 'client_address' ) );
		$intervention   = sanitize_text_field( (string) $request->get_param( 'intervention_type' ) );
		$notes          = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$gdpr_accepted  = (bool) $request->get_param( 'gdpr_accepted' );
		$nonce          = (string) $request->get_param( 'booking_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'mp_agenda_booking' ) ) {
			return new WP_Error( 'mp_agenda_invalid_nonce', __( 'Requête invalide, veuillez recharger la page.', 'mp-agenda' ), array( 'status' => 403 ) );
		}

		if ( ! $technician_id || ! $date || ! $time || ! $client_name || ! $client_phone || ! $client_address ) {
			return new WP_Error( 'mp_agenda_invalid', __( 'Merci de remplir tous les champs obligatoires.', 'mp-agenda' ), array( 'status' => 400 ) );
		}

		if ( ! $gdpr_accepted ) {
			return new WP_Error( 'mp_agenda_gdpr_required', __( 'Vous devez accepter la mention RGPD.', 'mp-agenda' ), array( 'status' => 400 ) );
		}

		$technician = MP_Agenda_DB::get_technician( $technician_id );
		if ( ! $technician || empty( $technician['is_active'] ) ) {
			return new WP_Error( 'mp_agenda_not_found', __( 'Technicien introuvable.', 'mp-agenda' ), array( 'status' => 404 ) );
		}

		$start_dt = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time );
		if ( ! $start_dt ) {
			return new WP_Error( 'mp_agenda_invalid', __( 'Date ou heure invalide.', 'mp-agenda' ), array( 'status' => 400 ) );
		}
		$end_dt = clone $start_dt;
		$end_dt->modify( '+' . $duration . ' minutes' );

		if ( ! MP_Agenda_DB::is_slot_available( $technician_id, $start_dt->format( 'Y-m-d H:i:s' ), $end_dt->format( 'Y-m-d H:i:s' ) ) ) {
			return new WP_Error( 'mp_agenda_slot_taken', __( 'Ce créneau n\'est plus disponible. Merci de choisir un autre horaire.', 'mp-agenda' ), array( 'status' => 409 ) );
		}

		$data = array(
			'technician_id'     => $technician_id,
			'client_name'       => $client_name,
			'client_email'      => $client_email,
			'client_phone'      => $client_phone,
			'client_address'    => $client_address,
			'intervention_type' => $intervention,
			'service_id'        => $service ? $service_id : null,
			'notes'             => $notes,
			'start_datetime'    => $start_dt->format( 'Y-m-d H:i:s' ),
			'end_datetime'      => $end_dt->format( 'Y-m-d H:i:s' ),
			'duration'          => $duration,
			'status'            => 'confirmed',
			'source'            => 'online',
		);

		$id = MP_Agenda_DB::save_appointment( $data );
		$appointment = MP_Agenda_DB::get_appointment( $id );

		$google_sync = new MP_Agenda_Google_Sync();
		$google_sync->push_appointment( $appointment );

		$notifications = new MP_Agenda_Notifications();
		$notifications->send_appointment_notifications( $appointment );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'appointment' => array(
					'id'                => $appointment['id'],
					'technician_name'   => $technician['name'],
					'start_datetime'    => $appointment['start_datetime'],
					'intervention_type' => $appointment['intervention_type'],
					'service_name'      => $appointment['service_name'],
				),
			),
			201
		);
	}

	/* ---------------------------------------------------------------------
	 * Google Agenda (admin)
	 * ------------------------------------------------------------------- */

	/**
	 * Force une synchronisation manuelle de tous les techniciens connectés à Google.
	 *
	 * @return WP_REST_Response
	 */
	public function force_google_sync() {
		$google_sync = new MP_Agenda_Google_Sync();
		$google_sync->sync_all();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Déconnecte le compte Google d'un technicien via l'API REST.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function disconnect_google( WP_REST_Request $request ) {
		$technician_id = absint( $request->get_param( 'technician_id' ) );

		MP_Agenda_DB::save_technician(
			array(
				'google_calendar_id'      => null,
				'google_access_token'     => null,
				'google_refresh_token'    => null,
				'google_token_expires_at' => null,
			),
			$technician_id
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
