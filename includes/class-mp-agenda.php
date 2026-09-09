<?php
/**
 * Classe principale orchestrant le plugin MP Agenda.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda.
 *
 * Enregistre tous les hooks WordPress nécessaires au fonctionnement du plugin :
 * traductions, intervalle cron personnalisé, admin, front, API REST et shortcode.
 */
class MP_Agenda {

	/**
	 * Instance de la couche d'administration.
	 *
	 * @var MP_Agenda_Admin
	 */
	protected $admin;

	/**
	 * Instance de la couche front-end.
	 *
	 * @var MP_Agenda_Public
	 */
	protected $public_area;

	/**
	 * Instance de l'API REST.
	 *
	 * @var MP_Agenda_REST_API
	 */
	protected $rest_api;

	/**
	 * Instance du transport admin-ajax.php (repli si l'API REST est bloquée côté hébergeur).
	 *
	 * @var MP_Agenda_Ajax
	 */
	protected $ajax;

	/**
	 * Instance de la synchronisation Google.
	 *
	 * @var MP_Agenda_Google_Sync
	 */
	protected $google_sync;

	/**
	 * Instance des notifications email.
	 *
	 * @var MP_Agenda_Notifications
	 */
	protected $notifications;

	/**
	 * Instance de l'envoi de SMS (OVH).
	 *
	 * @var MP_Agenda_SMS
	 */
	protected $sms;

	/**
	 * Constructeur : instancie les sous-modules du plugin.
	 */
	public function __construct() {
		$this->admin         = new MP_Agenda_Admin();
		$this->public_area   = new MP_Agenda_Public();
		$this->rest_api      = new MP_Agenda_REST_API();
		$this->ajax          = new MP_Agenda_Ajax( $this->rest_api );
		$this->google_sync   = new MP_Agenda_Google_Sync();
		$this->notifications = new MP_Agenda_Notifications();
		$this->sms           = new MP_Agenda_SMS();

		new MP_Agenda_Shortcode();
	}

	/**
	 * Enregistre tous les hooks WordPress du plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( 'MP_Agenda_Activator', 'maybe_upgrade' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected

		// Cron horaire : rappel email ~24 h avant le rendez-vous.
		add_action( 'mp_agenda_send_reminders_cron', array( $this->notifications, 'send_due_reminders' ) );

		// Même cron horaire : rappels SMS J-3 et J-1 (OVH).
		add_action( 'mp_agenda_send_reminders_cron', array( $this->sms, 'send_due_sms_reminders' ) );

		$this->admin->init();
		$this->public_area->init();
		$this->rest_api->init();
		$this->ajax->init();
		$this->google_sync->init();
	}

	/**
	 * Charge le domaine de traduction du plugin.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mp-agenda', false, dirname( MP_AGENDA_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Ajoute un intervalle cron de 5 minutes utilisé par la synchronisation Google.
	 *
	 * @param array $schedules Intervalles cron existants.
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['mp_agenda_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Toutes les 5 minutes (MP Agenda)', 'mp-agenda' ),
		);

		return $schedules;
	}
}
