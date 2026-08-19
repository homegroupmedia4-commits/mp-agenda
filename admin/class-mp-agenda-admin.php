<?php
/**
 * Gère l'interface d'administration du plugin MP Agenda.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Admin.
 */
class MP_Agenda_Admin {

	/**
	 * Enregistre les hooks liés à l'administration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'admin_post_mp_agenda_save_technician', array( $this, 'handle_save_technician' ) );
		add_action( 'admin_post_mp_agenda_delete_technician', array( $this, 'handle_delete_technician' ) );
		add_action( 'admin_post_mp_agenda_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_mp_agenda_save_intervention_types', array( $this, 'handle_save_intervention_types' ) );
		add_action( 'admin_post_mp_agenda_save_google_credentials', array( $this, 'handle_save_google_credentials' ) );
		add_action( 'admin_post_mp_agenda_google_disconnect', array( $this, 'handle_google_disconnect' ) );
		add_action( 'admin_post_mp_agenda_export_csv', array( $this, 'handle_export_csv' ) );
	}

	/**
	 * Enregistre les pages du menu d'administration.
	 *
	 * @return void
	 */
	public function register_menu() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'MP Agenda', 'mp-agenda' ),
			__( 'MP Agenda', 'mp-agenda' ),
			$capability,
			'mp-agenda',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			25
		);

		add_submenu_page( 'mp-agenda', __( 'Planning', 'mp-agenda' ), __( 'Planning', 'mp-agenda' ), $capability, 'mp-agenda', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'mp-agenda', __( 'Rendez-vous', 'mp-agenda' ), __( 'Rendez-vous', 'mp-agenda' ), $capability, 'mp-agenda-appointments', array( $this, 'render_appointments' ) );
		add_submenu_page( 'mp-agenda', __( 'Techniciens', 'mp-agenda' ), __( 'Techniciens', 'mp-agenda' ), $capability, 'mp-agenda-technicians', array( $this, 'render_technicians' ) );
		add_submenu_page( 'mp-agenda', __( 'Réglages', 'mp-agenda' ), __( 'Réglages', 'mp-agenda' ), $capability, 'mp-agenda-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Charge les CSS/JS admin uniquement sur les pages du plugin.
	 *
	 * @param string $hook Nom du hook de la page courante.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'mp-agenda' ) === false ) {
			return;
		}

		wp_enqueue_style( 'mp-agenda-admin', MP_AGENDA_PLUGIN_URL . 'admin/css/mp-agenda-admin.css', array(), MP_AGENDA_VERSION );
		wp_enqueue_script( 'mp-agenda-admin', MP_AGENDA_PLUGIN_URL . 'admin/js/mp-agenda-admin.js', array( 'wp-i18n' ), MP_AGENDA_VERSION, true );

		wp_localize_script(
			'mp-agenda-admin',
			'mpAgendaAdmin',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'mp-agenda/v1' ) ),
				'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'      => wp_create_nonce( 'mp_agenda_ajax' ),
				'i18n'       => array(
					'confirmDelete' => __( 'Voulez-vous vraiment supprimer ce rendez-vous ?', 'mp-agenda' ),
					'saveError'     => __( 'Une erreur est survenue lors de l\'enregistrement.', 'mp-agenda' ),
					'slotTaken'     => __( 'Ce créneau n\'est plus disponible.', 'mp-agenda' ),
				),
				'statusLabels' => array(
					'pending'   => __( 'En attente', 'mp-agenda' ),
					'confirmed' => __( 'Confirmé', 'mp-agenda' ),
					'cancelled' => __( 'Annulé', 'mp-agenda' ),
					'completed' => __( 'Terminé', 'mp-agenda' ),
				),
				'interventionTypes' => get_option( 'mp_agenda_intervention_types', array() ),
			)
		);

		wp_enqueue_media();
	}

	/**
	 * Vérifie les droits et le nonce pour les actions admin-post.
	 *
	 * @param string $action Nom de l'action (utilisé comme nom de nonce).
	 * @return void
	 */
	private function verify_request( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'mp-agenda' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Affiche la page Planning (dashboard).
	 *
	 * @return void
	 */
	public function render_dashboard() {
		require MP_AGENDA_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Affiche la page Liste des rendez-vous.
	 *
	 * @return void
	 */
	public function render_appointments() {
		require MP_AGENDA_PLUGIN_DIR . 'admin/views/appointments.php';
	}

	/**
	 * Affiche la page Techniciens.
	 *
	 * @return void
	 */
	public function render_technicians() {
		require MP_AGENDA_PLUGIN_DIR . 'admin/views/technicians.php';
	}

	/**
	 * Affiche la page Réglages.
	 *
	 * @return void
	 */
	public function render_settings() {
		require MP_AGENDA_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Traite l'enregistrement (création/modification) d'un technicien.
	 *
	 * @return void
	 */
	public function handle_save_technician() {
		$this->verify_request( 'mp_agenda_save_technician' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$working_hours = array();
		$days          = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		foreach ( $days as $day ) {
			$working_hours[ $day ] = array(
				'active' => isset( $_POST[ "active_{$day}" ] ) ? true : false,
				'start'  => isset( $_POST[ "start_{$day}" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "start_{$day}" ] ) ) : '08:00',
				'end'    => isset( $_POST[ "end_{$day}" ] ) ? sanitize_text_field( wp_unslash( $_POST[ "end_{$day}" ] ) ) : '18:00',
			);
		}

		$data = array(
			'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'         => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'photo_url'     => isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '',
			'zone'          => isset( $_POST['zone'] ) ? sanitize_text_field( wp_unslash( $_POST['zone'] ) ) : '',
			'working_hours' => wp_json_encode( $working_hours ),
			'is_active'     => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		MP_Agenda_DB::save_technician( $data, $id ?: null );

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-technicians', 'mp_agenda_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Traite la suppression d'un technicien.
	 *
	 * @return void
	 */
	public function handle_delete_technician() {
		$this->verify_request( 'mp_agenda_delete_technician' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id ) {
			MP_Agenda_DB::delete_technician( $id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-technicians', 'mp_agenda_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Traite l'enregistrement des réglages généraux.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->verify_request( 'mp_agenda_save_settings' );

		$settings = array(
			'company_name'               => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'notification_email'         => isset( $_POST['notification_email'] ) ? sanitize_email( wp_unslash( $_POST['notification_email'] ) ) : '',
			'default_duration'           => isset( $_POST['default_duration'] ) ? absint( $_POST['default_duration'] ) : 60,
			'timezone'                   => isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : wp_timezone_string(),
			'notify_client'              => isset( $_POST['notify_client'] ) ? 1 : 0,
			'notify_technician'          => isset( $_POST['notify_technician'] ) ? 1 : 0,
			'require_technician_choice' => isset( $_POST['require_technician_choice'] ) ? 1 : 0,
		);

		update_option( 'mp_agenda_settings', $settings );

		if ( isset( $_POST['gdpr_text'] ) ) {
			update_option( 'mp_agenda_gdpr_text', wp_kses_post( wp_unslash( $_POST['gdpr_text'] ) ) );
		}
		if ( isset( $_POST['gdpr_retention_months'] ) ) {
			update_option( 'mp_agenda_gdpr_retention_months', absint( $_POST['gdpr_retention_months'] ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-settings', 'mp_agenda_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Traite l'enregistrement des types d'intervention.
	 *
	 * @return void
	 */
	public function handle_save_intervention_types() {
		$this->verify_request( 'mp_agenda_save_intervention_types' );

		$types_raw = isset( $_POST['intervention_types'] ) ? wp_unslash( $_POST['intervention_types'] ) : array();
		$types     = array();

		if ( is_array( $types_raw ) ) {
			foreach ( $types_raw as $type ) {
				$type = sanitize_text_field( $type );
				if ( '' !== $type ) {
					$types[] = $type;
				}
			}
		}

		update_option( 'mp_agenda_intervention_types', $types );

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-settings', 'mp_agenda_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Traite l'enregistrement des identifiants API Google.
	 *
	 * @return void
	 */
	public function handle_save_google_credentials() {
		$this->verify_request( 'mp_agenda_save_google_credentials' );

		if ( isset( $_POST['google_client_id'] ) ) {
			update_option( 'mp_agenda_google_client_id', sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) );
		}
		if ( isset( $_POST['google_client_secret'] ) ) {
			update_option( 'mp_agenda_google_client_secret', sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-settings', 'mp_agenda_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Déconnecte le compte Google d'un technicien.
	 *
	 * @return void
	 */
	public function handle_google_disconnect() {
		$this->verify_request( 'mp_agenda_google_disconnect' );

		$technician_id = isset( $_POST['technician_id'] ) ? absint( $_POST['technician_id'] ) : 0;

		if ( $technician_id ) {
			MP_Agenda_DB::save_technician(
				array(
					'google_calendar_id'      => null,
					'google_access_token'     => null,
					'google_refresh_token'    => null,
					'google_token_expires_at' => null,
				),
				$technician_id
			);
			delete_option( 'mp_agenda_google_last_sync_' . $technician_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mp-agenda-technicians', 'mp_agenda_notice' => 'disconnected' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Génère et télécharge un export CSV des rendez-vous filtrés.
	 *
	 * @return void
	 */
	public function handle_export_csv() {
		$this->verify_request( 'mp_agenda_export_csv' );

		$args = array(
			'from'          => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'to'            => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'technician_id' => isset( $_GET['technician_id'] ) ? absint( $_GET['technician_id'] ) : 0,
			'status'        => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
			'search'        => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);

		$appointments = MP_Agenda_DB::get_appointments( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mp-agenda-rendez-vous-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		// BOM UTF-8 pour une ouverture correcte dans Excel.
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, array( 'Date', 'Heure', 'Technicien', 'Client', 'Téléphone', 'Email', 'Type', 'Statut', 'Source' ) );

		foreach ( $appointments as $appointment ) {
			$date = new DateTime( $appointment['start_datetime'] );
			fputcsv(
				$output,
				array(
					$date->format( 'd/m/Y' ),
					$date->format( 'H:i' ),
					$appointment['technician_name'],
					$appointment['client_name'],
					$appointment['client_phone'],
					$appointment['client_email'],
					$appointment['intervention_type'],
					$appointment['status'],
					$appointment['source'],
				)
			);
		}

		fclose( $output );
		exit;
	}
}
