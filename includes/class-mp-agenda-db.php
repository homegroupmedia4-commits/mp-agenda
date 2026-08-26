<?php
/**
 * Couche d'accès à la base de données pour MP Agenda.
 *
 * Centralise toutes les requêtes SQL (CRUD) pour les techniciens,
 * les rendez-vous et les créneaux bloqués.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_DB.
 */
class MP_Agenda_DB {

	/**
	 * Retourne le nom complet de la table des techniciens.
	 *
	 * @return string
	 */
	public static function table_technicians() {
		global $wpdb;
		return $wpdb->prefix . 'mp_agenda_technicians';
	}

	/**
	 * Retourne le nom complet de la table des rendez-vous.
	 *
	 * @return string
	 */
	public static function table_appointments() {
		global $wpdb;
		return $wpdb->prefix . 'mp_agenda_appointments';
	}

	/**
	 * Retourne le nom complet de la table des créneaux bloqués.
	 *
	 * @return string
	 */
	public static function table_blocked_slots() {
		global $wpdb;
		return $wpdb->prefix . 'mp_agenda_blocked_slots';
	}

	/**
	 * Retourne le nom complet de la table des showrooms.
	 *
	 * @return string
	 */
	public static function table_showrooms() {
		global $wpdb;
		return $wpdb->prefix . 'mp_agenda_showrooms';
	}

	/**
	 * Retourne le nom complet de la table des services.
	 *
	 * @return string
	 */
	public static function table_services() {
		global $wpdb;
		return $wpdb->prefix . 'mp_agenda_services';
	}

	/* ---------------------------------------------------------------------
	 * TECHNICIENS
	 * ------------------------------------------------------------------- */

	/**
	 * Récupère la liste des techniciens.
	 *
	 * @param bool $active_only Ne retourner que les techniciens actifs.
	 * @return array
	 */
	public static function get_technicians( $active_only = false ) {
		global $wpdb;
		$table = self::table_technicians();

		if ( $active_only ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE is_active = %d ORDER BY name ASC", 1 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT * FROM {$table} ORDER BY name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Récupère les techniciens associés à un showroom donné, en incluant ceux
	 * sans showroom_id (NULL = rattaché à tous les showrooms).
	 *
	 * @param int  $showroom_id ID du showroom.
	 * @param bool $active_only Ne retourner que les techniciens actifs.
	 * @return array
	 */
	public static function get_technicians_by_showroom( $showroom_id, $active_only = true ) {
		global $wpdb;
		$table = self::table_technicians();

		$where  = array( '(showroom_id = %d OR showroom_id IS NULL)' );
		$params = array( absint( $showroom_id ) );

		if ( $active_only ) {
			$where[]  = 'is_active = %d';
			$params[] = 1;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY name ASC'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Récupère un technicien par son ID.
	 *
	 * @param int $id ID du technicien.
	 * @return array|null
	 */
	public static function get_technician( $id ) {
		global $wpdb;
		$table = self::table_technicians();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Crée ou met à jour un technicien.
	 *
	 * @param array    $data Données du technicien.
	 * @param int|null $id   ID du technicien (null pour une création).
	 * @return int ID du technicien créé ou mis à jour.
	 */
	public static function save_technician( $data, $id = null ) {
		global $wpdb;
		$table = self::table_technicians();

		$data['updated_at'] = current_time( 'mysql' );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery
			return absint( $id );
		}

		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Supprime un technicien (et par cascade applicative ses RDV/créneaux bloqués).
	 *
	 * @param int $id ID du technicien.
	 * @return void
	 */
	public static function delete_technician( $id ) {
		global $wpdb;
		$id = absint( $id );

		$wpdb->delete( self::table_appointments(), array( 'technician_id' => $id ) );
		$wpdb->delete( self::table_blocked_slots(), array( 'technician_id' => $id ) );
		$wpdb->delete( self::table_technicians(), array( 'id' => $id ) );
	}

	/* ---------------------------------------------------------------------
	 * RENDEZ-VOUS
	 * ------------------------------------------------------------------- */

	/**
	 * Récupère une liste de rendez-vous filtrée.
	 *
	 * @param array $args Filtres possibles : from, to, technician_id, status, search, orderby, order, page, per_page.
	 * @return array
	 */
	public static function get_appointments( $args = array() ) {
		global $wpdb;
		$table    = self::table_appointments();
		$tech     = self::table_technicians();
		$services = self::table_services();

		$defaults = array(
			'from'          => '',
			'to'            => '',
			'technician_id' => 0,
			'status'        => '',
			'search'        => '',
			'orderby'       => 'start_datetime',
			'order'         => 'ASC',
			'page'          => 1,
			'per_page'      => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['from'] ) {
			$where[]  = 'a.start_datetime >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}

		if ( $args['to'] ) {
			$where[]  = 'a.start_datetime <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}

		if ( $args['technician_id'] ) {
			$where[]  = 'a.technician_id = %d';
			$params[] = absint( $args['technician_id'] );
		}

		if ( $args['status'] ) {
			$where[]  = 'a.status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( $args['search'] ) {
			$where[]  = '(a.client_name LIKE %s OR a.client_phone LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$orderby_allowed = array( 'start_datetime', 'client_name', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $orderby_allowed, true ) ? $args['orderby'] : 'start_datetime';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$sql = "SELECT a.*, t.name AS technician_name, s.name AS service_name FROM {$table} a
			LEFT JOIN {$tech} t ON t.id = a.technician_id
			LEFT JOIN {$services} s ON s.id = a.service_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY a.{$orderby} {$order}";

		if ( $args['per_page'] > 0 ) {
			$offset  = ( max( 1, absint( $args['page'] ) ) - 1 ) * absint( $args['per_page'] );
			$sql    .= ' LIMIT %d OFFSET %d';
			$params[] = absint( $args['per_page'] );
			$params[] = $offset;
		}

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Compte le nombre total de rendez-vous correspondant aux filtres (pour la pagination).
	 *
	 * @param array $args Mêmes filtres que get_appointments().
	 * @return int
	 */
	public static function count_appointments( $args = array() ) {
		global $wpdb;
		$table = self::table_appointments();

		$defaults = array(
			'from'          => '',
			'to'            => '',
			'technician_id' => 0,
			'status'        => '',
			'search'        => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['from'] ) {
			$where[]  = 'start_datetime >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( $args['to'] ) {
			$where[]  = 'start_datetime <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}
		if ( $args['technician_id'] ) {
			$where[]  = 'technician_id = %d';
			$params[] = absint( $args['technician_id'] );
		}
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( $args['search'] ) {
			$where[]  = '(client_name LIKE %s OR client_phone LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Récupère un rendez-vous par son ID.
	 *
	 * @param int $id ID du rendez-vous.
	 * @return array|null
	 */
	public static function get_appointment( $id ) {
		global $wpdb;
		$table    = self::table_appointments();
		$tech     = self::table_technicians();
		$services = self::table_services();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, t.name AS technician_name, s.name AS service_name FROM {$table} a
				LEFT JOIN {$tech} t ON t.id = a.technician_id
				LEFT JOIN {$services} s ON s.id = a.service_id
				WHERE a.id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $id )
			),
			ARRAY_A
		);
	}

	/**
	 * Récupère un rendez-vous par son token d'annulation.
	 *
	 * @param string $token Token d'annulation.
	 * @return array|null
	 */
	public static function get_appointment_by_token( $token ) {
		global $wpdb;
		$table = self::table_appointments();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE cancel_token = %s", sanitize_text_field( $token ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Crée ou met à jour un rendez-vous.
	 *
	 * @param array    $data Données du rendez-vous.
	 * @param int|null $id   ID du rendez-vous (null pour une création).
	 * @return int ID du rendez-vous créé ou mis à jour.
	 */
	public static function save_appointment( $data, $id = null ) {
		global $wpdb;
		$table = self::table_appointments();

		$data['updated_at'] = current_time( 'mysql' );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery
			return absint( $id );
		}

		$data['created_at'] = current_time( 'mysql' );
		if ( empty( $data['cancel_token'] ) ) {
			$data['cancel_token'] = wp_generate_password( 32, false );
		}

		$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Supprime un rendez-vous.
	 *
	 * @param int $id ID du rendez-vous.
	 * @return void
	 */
	public static function delete_appointment( $id ) {
		global $wpdb;
		$wpdb->delete( self::table_appointments(), array( 'id' => absint( $id ) ) );
	}

	/**
	 * Vérifie si un créneau est disponible pour un technicien donné
	 * (aucun rendez-vous actif ni créneau bloqué en conflit).
	 *
	 * @param int      $technician_id ID du technicien.
	 * @param string   $start         Date/heure de début (Y-m-d H:i:s).
	 * @param string   $end           Date/heure de fin (Y-m-d H:i:s).
	 * @param int|null $exclude_appointment_id ID de RDV à exclure (cas d'une modification).
	 * @return bool True si le créneau est libre.
	 */
	public static function is_slot_available( $technician_id, $start, $end, $exclude_appointment_id = null ) {
		global $wpdb;
		$appointments_table = self::table_appointments();
		$blocked_table      = self::table_blocked_slots();

		$sql = "SELECT COUNT(*) FROM {$appointments_table}
			WHERE technician_id = %d
			AND status IN ('pending', 'confirmed')
			AND start_datetime < %s
			AND end_datetime > %s";
		$params = array( absint( $technician_id ), $end, $start );

		if ( $exclude_appointment_id ) {
			$sql     .= ' AND id != %d';
			$params[] = absint( $exclude_appointment_id );
		}

		$conflicting_appointments = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $conflicting_appointments > 0 ) {
			return false;
		}

		$conflicting_blocks = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$blocked_table} WHERE technician_id = %d AND start_datetime < %s AND end_datetime > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $technician_id ),
				$end,
				$start
			)
		);

		return 0 === $conflicting_blocks;
	}

	/**
	 * Récupère les rendez-vous et créneaux bloqués d'un technicien sur une plage de dates,
	 * utilisé pour calculer les disponibilités.
	 *
	 * @param int    $technician_id ID du technicien.
	 * @param string $from          Date de début (Y-m-d).
	 * @param string $to            Date de fin (Y-m-d).
	 * @return array
	 */
	public static function get_busy_slots( $technician_id, $from, $to ) {
		global $wpdb;
		$appointments_table = self::table_appointments();
		$blocked_table      = self::table_blocked_slots();

		$appointments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime, end_datetime FROM {$appointments_table}
				WHERE technician_id = %d AND status IN ('pending','confirmed')
				AND start_datetime <= %s AND end_datetime >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $technician_id ),
				$to . ' 23:59:59',
				$from . ' 00:00:00'
			),
			ARRAY_A
		);

		$blocked = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime, end_datetime FROM {$blocked_table}
				WHERE technician_id = %d
				AND start_datetime <= %s AND end_datetime >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $technician_id ),
				$to . ' 23:59:59',
				$from . ' 00:00:00'
			),
			ARRAY_A
		);

		return array_merge( $appointments, $blocked );
	}

	/* ---------------------------------------------------------------------
	 * CRÉNEAUX BLOQUÉS
	 * ------------------------------------------------------------------- */

	/**
	 * Crée un créneau bloqué.
	 *
	 * @param array $data Données du créneau bloqué.
	 * @return int ID du créneau créé.
	 */
	public static function create_blocked_slot( $data ) {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table_blocked_slots(), $data ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour un créneau bloqué à partir de son google_event_id.
	 *
	 * @param string $google_event_id ID de l'événement Google.
	 * @param array  $data            Données à mettre à jour.
	 * @return void
	 */
	public static function update_blocked_slot_by_google_id( $google_event_id, $data ) {
		global $wpdb;
		$wpdb->update( self::table_blocked_slots(), $data, array( 'google_event_id' => sanitize_text_field( $google_event_id ) ) );
	}

	/**
	 * Récupère un créneau bloqué par son google_event_id.
	 *
	 * @param string $google_event_id ID de l'événement Google.
	 * @return array|null
	 */
	public static function get_blocked_slot_by_google_id( $google_event_id ) {
		global $wpdb;
		$table = self::table_blocked_slots();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE google_event_id = %s", sanitize_text_field( $google_event_id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Récupère un rendez-vous par son google_event_id.
	 *
	 * @param string $google_event_id ID de l'événement Google.
	 * @return array|null
	 */
	public static function get_appointment_by_google_id( $google_event_id ) {
		global $wpdb;
		$table = self::table_appointments();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE google_event_id = %s", sanitize_text_field( $google_event_id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Supprime un créneau bloqué par son google_event_id.
	 *
	 * @param string $google_event_id ID de l'événement Google.
	 * @return void
	 */
	public static function delete_blocked_slot_by_google_id( $google_event_id ) {
		global $wpdb;
		$wpdb->delete( self::table_blocked_slots(), array( 'google_event_id' => sanitize_text_field( $google_event_id ) ) );
	}

	/* ---------------------------------------------------------------------
	 * SHOWROOMS
	 * ------------------------------------------------------------------- */

	/**
	 * Récupère la liste des showrooms.
	 *
	 * @param bool $active_only Ne retourner que les showrooms actifs.
	 * @return array
	 */
	public static function get_showrooms( $active_only = false ) {
		global $wpdb;
		$table = self::table_showrooms();

		if ( $active_only ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE is_active = %d ORDER BY display_order ASC, name ASC", 1 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT * FROM {$table} ORDER BY display_order ASC, name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Récupère un showroom par son ID.
	 *
	 * @param int $id ID du showroom.
	 * @return array|null
	 */
	public static function get_showroom( $id ) {
		global $wpdb;
		$table = self::table_showrooms();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Crée un showroom.
	 *
	 * @param array $data Données du showroom.
	 * @return int ID du showroom créé.
	 */
	public static function create_showroom( $data ) {
		global $wpdb;

		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->insert( self::table_showrooms(), $data ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour un showroom existant.
	 *
	 * @param int   $id   ID du showroom.
	 * @param array $data Données à mettre à jour.
	 * @return int ID du showroom mis à jour.
	 */
	public static function update_showroom( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->update( self::table_showrooms(), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return absint( $id );
	}

	/**
	 * Supprime un showroom. Les techniciens qui y étaient rattachés repassent
	 * automatiquement à "tous les showrooms" (showroom_id = NULL).
	 *
	 * @param int $id ID du showroom.
	 * @return void
	 */
	public static function delete_showroom( $id ) {
		global $wpdb;
		$id = absint( $id );

		$wpdb->update( self::table_technicians(), array( 'showroom_id' => null ), array( 'showroom_id' => $id ) );
		$wpdb->delete( self::table_showrooms(), array( 'id' => $id ) );
	}

	/* ---------------------------------------------------------------------
	 * SERVICES
	 * ------------------------------------------------------------------- */

	/**
	 * Récupère la liste des services.
	 *
	 * @param bool $active_only Ne retourner que les services actifs.
	 * @return array
	 */
	public static function get_services( $active_only = false ) {
		global $wpdb;
		$table = self::table_services();

		if ( $active_only ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE is_active = %d ORDER BY display_order ASC, name ASC", 1 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT * FROM {$table} ORDER BY display_order ASC, name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Récupère un service par son ID.
	 *
	 * @param int $id ID du service.
	 * @return array|null
	 */
	public static function get_service( $id ) {
		global $wpdb;
		$table = self::table_services();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Crée un service.
	 *
	 * @param array $data Données du service.
	 * @return int ID du service créé.
	 */
	public static function create_service( $data ) {
		global $wpdb;

		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->insert( self::table_services(), $data ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour un service existant.
	 *
	 * @param int   $id   ID du service.
	 * @param array $data Données à mettre à jour.
	 * @return int ID du service mis à jour.
	 */
	public static function update_service( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->update( self::table_services(), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.SlowDBQuery

		return absint( $id );
	}

	/**
	 * Supprime un service. Les rendez-vous qui y étaient rattachés conservent
	 * leur intervention_type d'origine (service_id repasse à NULL).
	 *
	 * @param int $id ID du service.
	 * @return void
	 */
	public static function delete_service( $id ) {
		global $wpdb;
		$id = absint( $id );

		$wpdb->update( self::table_appointments(), array( 'service_id' => null ), array( 'service_id' => $id ) );
		$wpdb->delete( self::table_services(), array( 'id' => $id ) );
	}
}
