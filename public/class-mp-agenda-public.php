<?php
/**
 * Gère les scripts et styles du front-end de MP Agenda.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Public.
 */
class MP_Agenda_Public {

	/**
	 * Enregistre les hooks front-end.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Charge les CSS/JS du formulaire de réservation.
	 *
	 * Chargés systématiquement sur le front (fichiers légers) afin de fonctionner
	 * quel que soit le moment où le shortcode [mp_agenda_booking] est rendu.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'mp-agenda-public', MP_AGENDA_PLUGIN_URL . 'public/css/mp-agenda-public.css', array(), MP_AGENDA_VERSION );
		wp_enqueue_script( 'mp-agenda-public', MP_AGENDA_PLUGIN_URL . 'public/js/mp-agenda-public.js', array(), MP_AGENDA_VERSION, true );

		$settings = get_option( 'mp_agenda_settings', array() );

		wp_localize_script(
			'mp-agenda-public',
			'mpAgendaPublic',
			array(
				'restUrl'                => esc_url_raw( rest_url( 'mp-agenda/v1' ) ),
				'bookingNonce'            => wp_create_nonce( 'mp_agenda_booking' ),
				'requireTechnicianChoice' => ! empty( $settings['require_technician_choice'] ),
				'gdprText'                => wp_kses_post( get_option( 'mp_agenda_gdpr_text', '' ) ),
				'i18n'                    => array(
					'loading'        => __( 'Chargement…', 'mp-agenda' ),
					'noSlots'        => __( 'Aucun créneau disponible ce jour-là.', 'mp-agenda' ),
					'selectSlot'     => __( 'Merci de choisir un créneau.', 'mp-agenda' ),
					'requiredFields' => __( 'Merci de remplir tous les champs obligatoires.', 'mp-agenda' ),
					'gdprRequired'   => __( 'Merci d\'accepter la mention RGPD.', 'mp-agenda' ),
					'genericError'   => __( 'Une erreur est survenue. Merci de réessayer.', 'mp-agenda' ),
					'confirmed'      => __( 'Votre rendez-vous est confirmé !', 'mp-agenda' ),
					'anyTechnician'  => __( 'Peu importe', 'mp-agenda' ),
					'months'         => array( 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ),
					'days'           => array( 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam' ),
				),
			)
		);
	}
}
