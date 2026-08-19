<?php
/**
 * Transport admin-ajax.php pour l'API de MP Agenda.
 *
 * Sert de pont entre admin-ajax.php et les méthodes métier de MP_Agenda_REST_API,
 * pour les hébergeurs qui bloquent /wp-json/. La logique métier reste unique
 * (portée par MP_Agenda_REST_API) ; seul le transport HTTP change.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Ajax.
 */
class MP_Agenda_Ajax {

	/**
	 * Instance de l'API REST dont on réutilise les méthodes métier et permission_callback.
	 *
	 * @var MP_Agenda_REST_API
	 */
	private $rest_api;

	/**
	 * Constructeur.
	 *
	 * @param MP_Agenda_REST_API $rest_api Instance partagée de l'API REST.
	 */
	public function __construct( MP_Agenda_REST_API $rest_api ) {
		$this->rest_api = $rest_api;
	}

	/**
	 * Enregistre les hooks admin-ajax (utilisateurs connectés et invités).
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_mp_agenda_api', array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_mp_agenda_api', array( $this, 'handle_request' ) );
	}

	/**
	 * Point d'entrée unique admin-ajax : reconstitue une WP_REST_Request à partir
	 * de la requête AJAX puis délègue à la même méthode que la route REST équivalente.
	 *
	 * @return void
	 */
	public function handle_request() {
		if ( ! check_ajax_referer( 'mp_agenda_ajax', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'mp_agenda_invalid_nonce',
					'message' => __( 'Session expirée, veuillez recharger la page.', 'mp-agenda' ),
				),
				403
			);
		}

		$raw_route = isset( $_POST['route'] ) ? sanitize_text_field( wp_unslash( $_POST['route'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$method    = isset( $_POST['method'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['method'] ) ) ) : 'GET'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$parts        = explode( '?', $raw_route, 2 );
		$route        = '/' . ltrim( $parts[0], '/' );
		$query_params = array();
		if ( isset( $parts[1] ) ) {
			wp_parse_str( $parts[1], $query_params );
		}

		$route_def = $this->match_route( $route, $method );

		if ( ! $route_def ) {
			wp_send_json_error(
				array(
					'code'    => 'mp_agenda_route_not_found',
					'message' => __( 'Route inconnue.', 'mp-agenda' ),
				),
				404
			);
		}

		list( $callback, $permission, $route_params ) = $route_def;

		if ( 'admin' === $permission ) {
			if ( ! $this->rest_api->admin_permission_callback() ) {
				wp_send_json_error(
					array(
						'code'    => 'mp_agenda_forbidden',
						'message' => __( 'Action non autorisée.', 'mp-agenda' ),
					),
					403
				);
			}
		} else {
			$allowed = $this->rest_api->public_permission_callback();
			if ( is_wp_error( $allowed ) ) {
				$error_data = $allowed->get_error_data();
				$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? $error_data['status'] : 429;
				wp_send_json_error(
					array(
						'code'    => $allowed->get_error_code(),
						'message' => $allowed->get_error_message(),
					),
					$status
				);
			}
		}

		$request = new WP_REST_Request( $method, $route );

		foreach ( $route_params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		foreach ( $query_params as $key => $value ) {
			$request->set_param( sanitize_key( $key ), $value );
		}

		if ( ! empty( $_POST['data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$decoded = json_decode( wp_unslash( $_POST['data'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
		}

		$response = call_user_func( $callback, $request );

		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? $error_data['status'] : 500;
			wp_send_json_error(
				array(
					'code'    => $response->get_error_code(),
					'message' => $response->get_error_message(),
				),
				$status
			);
		}

		if ( $response instanceof WP_REST_Response ) {
			$status = $response->get_status();
			$data   = $response->get_data();

			if ( $status >= 400 ) {
				wp_send_json_error( $data, $status );
			}

			wp_send_json_success( $data, $status );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Fait correspondre une route + méthode HTTP à la méthode MP_Agenda_REST_API
	 * équivalente et au type de permission requis, en miroir de register_routes().
	 *
	 * @param string $route  Chemin de la route (ex. "/appointments/12").
	 * @param string $method Méthode HTTP (GET, POST, PUT, DELETE).
	 * @return array|null Tableau [callback, permission ('admin'|'public'), route_params] ou null si aucune correspondance.
	 */
	private function match_route( $route, $method ) {
		$static_routes = array(
			'GET /appointments'       => array( 'get_appointments', 'admin' ),
			'POST /appointments'      => array( 'create_appointment', 'admin' ),
			'GET /blocked-slots'      => array( 'get_blocked_slots', 'admin' ),
			'GET /technicians'        => array( 'get_technicians', 'public' ),
			'GET /available-slots'    => array( 'get_available_slots', 'public' ),
			'POST /book'              => array( 'book_appointment', 'public' ),
			'POST /google/sync'       => array( 'force_google_sync', 'admin' ),
			'POST /google/disconnect' => array( 'disconnect_google', 'admin' ),
		);

		$key = $method . ' ' . $route;
		if ( isset( $static_routes[ $key ] ) ) {
			return array(
				array( $this->rest_api, $static_routes[ $key ][0] ),
				$static_routes[ $key ][1],
				array(),
			);
		}

		if ( preg_match( '#^/appointments/(\d+)$#', $route, $matches ) ) {
			$map = array(
				'GET'    => 'get_appointment',
				'PUT'    => 'update_appointment',
				'DELETE' => 'delete_appointment',
			);
			if ( isset( $map[ $method ] ) ) {
				return array( array( $this->rest_api, $map[ $method ] ), 'admin', array( 'id' => absint( $matches[1] ) ) );
			}
		}

		if ( 'GET' === $method && preg_match( '#^/technicians/(\d+)$#', $route, $matches ) ) {
			return array( array( $this->rest_api, 'get_technician' ), 'public', array( 'id' => absint( $matches[1] ) ) );
		}

		return null;
	}
}
