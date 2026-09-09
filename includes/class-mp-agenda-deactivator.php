<?php
/**
 * Gère la désactivation du plugin.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Deactivator.
 */
class MP_Agenda_Deactivator {

	/**
	 * Exécuté lors de la désactivation du plugin.
	 *
	 * Ne supprime aucune donnée (les tables et options restent en base) :
	 * seules les tâches cron de synchronisation Google, de vérification des
	 * tokens et d'envoi des rappels sont déprogrammées.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'mp_agenda_google_sync_cron' );
		wp_clear_scheduled_hook( 'mp_agenda_google_token_check_cron' );
		wp_clear_scheduled_hook( 'mp_agenda_send_reminders_cron' );
	}
}
