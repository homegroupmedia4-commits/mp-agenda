<?php
/**
 * Plugin Name:       MP Agenda
 * Plugin URI:         https://mp-renov.fr/
 * Description:       Système de prise de rendez-vous simplifié pour MP Rénov, avec planning visuel, formulaire client et synchronisation Google Agenda.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:             MP Rénov
 * Text Domain:       mp-agenda
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MP_Agenda
 */

// Empêche l'accès direct au fichier.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes globales du plugin.
 */
define( 'MP_AGENDA_VERSION', '1.0.0' );
define( 'MP_AGENDA_DB_VERSION', '1.0.0' );
define( 'MP_AGENDA_PLUGIN_FILE', __FILE__ );
define( 'MP_AGENDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MP_AGENDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MP_AGENDA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Chargement des classes du plugin.
 */
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-activator.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-deactivator.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-db.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-google-sync.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-notifications.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-rest-api.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda-shortcode.php';
require_once MP_AGENDA_PLUGIN_DIR . 'includes/class-mp-agenda.php';
require_once MP_AGENDA_PLUGIN_DIR . 'admin/class-mp-agenda-admin.php';
require_once MP_AGENDA_PLUGIN_DIR . 'public/class-mp-agenda-public.php';

/**
 * Hooks d'activation et de désactivation.
 */
register_activation_hook( __FILE__, array( 'MP_Agenda_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MP_Agenda_Deactivator', 'deactivate' ) );

/**
 * Démarre le plugin une fois que tous les plugins sont chargés.
 */
function mp_agenda_run() {
	$plugin = new MP_Agenda();
	$plugin->run();
}
add_action( 'plugins_loaded', 'mp_agenda_run' );
