<?php
/**
 * Nettoyage complet à la désinstallation du plugin MP Agenda.
 *
 * Supprime les tables, les options et les métadonnées créées par le plugin.
 * Ce fichier est appelé automatiquement par WordPress lors de la suppression
 * définitive du plugin (pas simplement sa désactivation).
 *
 * @package MP_Agenda
 */

// Sécurité : ce fichier ne doit être exécuté que par WordPress lors de la désinstallation.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Ne supprime les données que si l'option de conservation n'est pas activée.
$keep_data = get_option( 'mp_agenda_keep_data_on_uninstall', false );

if ( ! $keep_data ) {
	$tables = array(
		$wpdb->prefix . 'mp_agenda_appointments',
		$wpdb->prefix . 'mp_agenda_blocked_slots',
		$wpdb->prefix . 'mp_agenda_technicians',
		$wpdb->prefix . 'mp_agenda_showrooms',
	);

	foreach ( $tables as $table ) {
		// La table technicians étant elle-même supprimée ci-dessous, sa colonne
		// showroom_id disparaît avec elle (pas besoin d'un ALTER TABLE ... DROP COLUMN séparé).
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$options = array(
		'mp_agenda_db_version',
		'mp_agenda_settings',
		'mp_agenda_intervention_types',
		'mp_agenda_google_client_id',
		'mp_agenda_google_client_secret',
		'mp_agenda_gdpr_text',
		'mp_agenda_gdpr_retention_months',
		'mp_agenda_keep_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Supprime les timestamps de dernière synchronisation Google par technicien.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'mp_agenda_google_last_sync_' ) . '%'
		)
	);

	// Nettoie les tâches cron planifiées.
	wp_clear_scheduled_hook( 'mp_agenda_google_sync_cron' );
}
