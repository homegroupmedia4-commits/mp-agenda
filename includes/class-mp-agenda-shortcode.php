<?php
/**
 * Enregistre le shortcode [mp_agenda_booking] affichant le formulaire client.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Shortcode.
 */
class MP_Agenda_Shortcode {

	/**
	 * Constructeur : enregistre le shortcode.
	 */
	public function __construct() {
		add_shortcode( 'mp_agenda_booking', array( $this, 'render' ) );
	}

	/**
	 * Rendu du shortcode [mp_agenda_booking].
	 *
	 * @param array $atts Attributs du shortcode.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'default_technician' => '',
			),
			$atts,
			'mp_agenda_booking'
		);

		ob_start();
		require MP_AGENDA_PLUGIN_DIR . 'public/views/booking-form.php';
		return ob_get_clean();
	}
}
