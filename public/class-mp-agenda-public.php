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
		add_action( 'wp_head', array( $this, 'noindex_manage_page' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_manage_page_from_search' ) );
	}

	/**
	 * Ajoute une balise robots "noindex" sur la page publique "Gérer mon rendez-vous"
	 * (page volontairement discrète, non listée dans les menus).
	 *
	 * @return void
	 */
	public function noindex_manage_page() {
		$manage_page_id = (int) get_option( 'mp_agenda_manage_page_id' );

		if ( $manage_page_id && is_page( $manage_page_id ) ) {
			echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
		}
	}

	/**
	 * Exclut la page "Gérer mon rendez-vous" des résultats de recherche du site.
	 *
	 * @param WP_Query $query Requête principale.
	 * @return void
	 */
	public function exclude_manage_page_from_search( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$manage_page_id = (int) get_option( 'mp_agenda_manage_page_id' );

		if ( $manage_page_id ) {
			$excluded = (array) $query->get( 'post__not_in' );
			$excluded[] = $manage_page_id;
			$query->set( 'post__not_in', $excluded );
		}
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
		wp_enqueue_script( 'mp-agenda-manage', MP_AGENDA_PLUGIN_URL . 'public/js/mp-agenda-manage.js', array(), MP_AGENDA_VERSION, true );

		$settings = get_option( 'mp_agenda_settings', array() );

		wp_localize_script(
			'mp-agenda-manage',
			'mpAgendaManage',
			array(
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'mp_agenda_ajax' ),
				'i18n'    => array(
					'loading'           => __( 'Chargement…', 'mp-agenda' ),
					'noSlots'           => __( 'Aucun créneau disponible ce jour-là.', 'mp-agenda' ),
					'genericError'      => __( 'Une erreur est survenue. Merci de réessayer.', 'mp-agenda' ),
					'confirmCancel'     => __( 'Confirmez-vous l\'annulation de ce rendez-vous ?', 'mp-agenda' ),
					'rescheduleSuccess' => __( 'Votre rendez-vous a bien été modifié. Un email de confirmation vous a été envoyé.', 'mp-agenda' ),
					'cancelSuccess'     => __( 'Votre rendez-vous a bien été annulé.', 'mp-agenda' ),
					'months'            => array( 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ),
					'days'              => array( 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam' ),
				),
			)
		);

		wp_localize_script(
			'mp-agenda-public',
			'mpAgendaPublic',
			array(
				'restUrl'                => esc_url_raw( rest_url( 'mp-agenda/v1' ) ),
				'ajaxUrl'                 => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'                   => wp_create_nonce( 'mp_agenda_ajax' ),
				'bookingNonce'            => wp_create_nonce( 'mp_agenda_booking' ),
				'requireTechnicianChoice' => ! empty( $settings['require_technician_choice'] ),
				'gdprText'                => wp_kses_post( get_option( 'mp_agenda_gdpr_text', '' ) ),
				'i18n'                    => array(
					'loading'        => __( 'Chargement…', 'mp-agenda' ),
					'noSlots'        => __( 'Aucun créneau disponible ce jour-là.', 'mp-agenda' ),
					'noShowrooms'    => __( 'Aucun showroom disponible pour le moment.', 'mp-agenda' ),
					'noServices'     => __( 'Aucun service disponible pour le moment.', 'mp-agenda' ),
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
