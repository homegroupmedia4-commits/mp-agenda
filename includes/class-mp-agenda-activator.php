<?php
/**
 * Gère l'activation du plugin : création des tables et options par défaut.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Activator.
 */
class MP_Agenda_Activator {

	/**
	 * Exécuté lors de l'activation du plugin.
	 *
	 * Crée les tables nécessaires, insère les techniciens par défaut
	 * (Alexandre et Kamal) et initialise les options du plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::seed_default_technicians();
		self::seed_default_showrooms();
		self::set_default_options();
		self::schedule_cron();

		update_option( 'mp_agenda_db_version', MP_AGENDA_DB_VERSION );
	}

	/**
	 * Exécute la mise à niveau des tables/données pour un plugin déjà actif
	 * (sans passer par activate()), déclenché quand MP_AGENDA_DB_VERSION change.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'mp_agenda_db_version' ) === MP_AGENDA_DB_VERSION ) {
			return;
		}

		self::create_tables();
		self::seed_default_showrooms();

		update_option( 'mp_agenda_db_version', MP_AGENDA_DB_VERSION );
	}

	/**
	 * Crée les tables SQL du plugin via dbDelta.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$table_technicians   = $wpdb->prefix . 'mp_agenda_technicians';
		$table_appointments  = $wpdb->prefix . 'mp_agenda_appointments';
		$table_blocked_slots = $wpdb->prefix . 'mp_agenda_blocked_slots';

		$sql_technicians = "CREATE TABLE {$table_technicians} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(30) DEFAULT NULL,
			photo_url VARCHAR(500) DEFAULT NULL,
			zone VARCHAR(255) DEFAULT NULL,
			google_calendar_id VARCHAR(255) DEFAULT NULL,
			google_access_token TEXT DEFAULT NULL,
			google_refresh_token TEXT DEFAULT NULL,
			google_token_expires_at DATETIME DEFAULT NULL,
			working_hours LONGTEXT DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql_appointments = "CREATE TABLE {$table_appointments} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			technician_id BIGINT UNSIGNED NOT NULL,
			client_name VARCHAR(200) NOT NULL,
			client_email VARCHAR(255) DEFAULT NULL,
			client_phone VARCHAR(30) NOT NULL,
			client_address TEXT DEFAULT NULL,
			intervention_type VARCHAR(100) DEFAULT NULL,
			surface VARCHAR(50) DEFAULT NULL,
			urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
			notes TEXT DEFAULT NULL,
			internal_notes TEXT DEFAULT NULL,
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NOT NULL,
			duration INT NOT NULL DEFAULT 60,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			google_event_id VARCHAR(255) DEFAULT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'admin',
			cancel_token VARCHAR(64) DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_tech_date (technician_id, start_datetime),
			KEY idx_status (status),
			KEY idx_google_event (google_event_id)
		) {$charset_collate};";

		$sql_blocked_slots = "CREATE TABLE {$table_blocked_slots} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			technician_id BIGINT UNSIGNED NOT NULL,
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NOT NULL,
			reason VARCHAR(255) DEFAULT NULL,
			google_event_id VARCHAR(255) DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_tech_date (technician_id, start_datetime),
			KEY idx_google_event (google_event_id)
		) {$charset_collate};";

		$table_showrooms = $wpdb->prefix . 'mp_agenda_showrooms';

		$sql_showrooms = "CREATE TABLE {$table_showrooms} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			address TEXT DEFAULT NULL,
			phone VARCHAR(30) DEFAULT NULL,
			photo_url VARCHAR(500) DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			display_order INT NOT NULL DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		// Note : les FOREIGN KEY ne sont pas gérées par dbDelta, l'intégrité
		// référentielle est donc assurée au niveau applicatif (class-mp-agenda-db.php).
		dbDelta( $sql_technicians );
		dbDelta( $sql_appointments );
		dbDelta( $sql_blocked_slots );
		dbDelta( $sql_showrooms );

		self::add_showroom_id_column();
	}

	/**
	 * Ajoute la colonne showroom_id à la table des techniciens si elle n'existe pas déjà
	 * (association technicien ↔ showroom, NULL = rattaché à tous les showrooms).
	 *
	 * @return void
	 */
	private static function add_showroom_id_column() {
		global $wpdb;

		$table  = $wpdb->prefix . 'mp_agenda_technicians';
		$exists = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'showroom_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN showroom_id BIGINT UNSIGNED DEFAULT NULL AFTER zone" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Crée les fiches des deux techniciens par défaut si elles n'existent pas.
	 *
	 * @return void
	 */
	private static function seed_default_technicians() {
		global $wpdb;

		$table = $wpdb->prefix . 'mp_agenda_technicians';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $count > 0 ) {
			return;
		}

		$default_hours = wp_json_encode(
			array(
				'mon' => array( 'active' => true, 'start' => '08:00', 'end' => '18:00' ),
				'tue' => array( 'active' => true, 'start' => '08:00', 'end' => '18:00' ),
				'wed' => array( 'active' => true, 'start' => '08:00', 'end' => '18:00' ),
				'thu' => array( 'active' => true, 'start' => '08:00', 'end' => '18:00' ),
				'fri' => array( 'active' => true, 'start' => '08:00', 'end' => '18:00' ),
				'sat' => array( 'active' => false, 'start' => '08:00', 'end' => '12:00' ),
				'sun' => array( 'active' => false, 'start' => '08:00', 'end' => '12:00' ),
			)
		);

		$technicians = array(
			array(
				'name'          => 'Alexandre',
				'email'         => 'alexandre@mp-renov.fr',
				'zone'          => '',
				'working_hours' => $default_hours,
				'is_active'     => 1,
			),
			array(
				'name'          => 'Kamal',
				'email'         => 'kamal@mp-renov.fr',
				'zone'          => '',
				'working_hours' => $default_hours,
				'is_active'     => 1,
			),
		);

		foreach ( $technicians as $technician ) {
			$wpdb->insert( $table, $technician ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery
		}
	}

	/**
	 * Crée les fiches des trois showrooms par défaut si aucun showroom n'existe encore.
	 *
	 * @return void
	 */
	private static function seed_default_showrooms() {
		global $wpdb;

		$table = $wpdb->prefix . 'mp_agenda_showrooms';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $count > 0 ) {
			return;
		}

		$showrooms = array(
			array(
				'name'          => 'Showroom Clamart',
				'address'       => '557 Avenue du Général de Gaulle, 92140 Clamart',
				'is_active'     => 1,
				'display_order' => 1,
			),
			array(
				'name'          => 'Showroom Massy',
				'address'       => '6 Rte de la Bonde, 91300 Massy',
				'is_active'     => 1,
				'display_order' => 2,
			),
			array(
				'name'          => 'Showroom Boulogne',
				'address'       => '34 Quai Alphonse le Gallo, 92100 Boulogne-Billancourt',
				'is_active'     => 1,
				'display_order' => 3,
			),
		);

		foreach ( $showrooms as $showroom ) {
			$wpdb->insert( $table, $showroom ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery
		}
	}

	/**
	 * Initialise les options du plugin avec des valeurs par défaut.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		if ( false === get_option( 'mp_agenda_settings' ) ) {
			add_option(
				'mp_agenda_settings',
				array(
					'company_name'          => 'MP Rénov',
					'notification_email'    => get_option( 'admin_email' ),
					'default_duration'      => 60,
					'timezone'              => wp_timezone_string(),
					'notify_client'         => 1,
					'notify_technician'     => 1,
					'require_technician_choice' => 0,
				)
			);
		}

		if ( false === get_option( 'mp_agenda_intervention_types' ) ) {
			add_option(
				'mp_agenda_intervention_types',
				array( 'Plomberie', 'Électricité', 'Peinture', 'Rénovation générale', 'Diagnostic', 'Autre' )
			);
		}

		if ( false === get_option( 'mp_agenda_gdpr_text' ) ) {
			add_option(
				'mp_agenda_gdpr_text',
				'En soumettant ce formulaire, vous acceptez que vos données personnelles soient collectées et utilisées par MP Rénov dans le cadre de la gestion de votre rendez-vous. Conformément au RGPD, vous disposez d\'un droit d\'accès, de rectification et de suppression de vos données.'
			);
		}

		if ( false === get_option( 'mp_agenda_gdpr_retention_months' ) ) {
			add_option( 'mp_agenda_gdpr_retention_months', 24 );
		}
	}

	/**
	 * Planifie la tâche cron de synchronisation Google Agenda (toutes les 5 minutes).
	 *
	 * @return void
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'mp_agenda_google_sync_cron' ) ) {
			wp_schedule_event( time(), 'mp_agenda_five_minutes', 'mp_agenda_google_sync_cron' );
		}
	}
}
