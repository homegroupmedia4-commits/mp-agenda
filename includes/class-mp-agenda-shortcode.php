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
		add_shortcode( 'mp_agenda_popup', array( $this, 'render_popup' ) );
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

	/**
	 * Rendu du shortcode [mp_agenda_popup].
	 * Affiche un bouton "Prendre RDV" qui ouvre le formulaire [mp_agenda_booking] dans une popup.
	 *
	 * @param array $atts Attributs du shortcode.
	 * @return string
	 */
	public function render_popup( $atts ) {
		static $instance = 0;
		$instance++;
		$overlay_id = 'rdvOverlay' . $instance;

		ob_start();
		?>
		<style>
			.rdv-btn {
				background: #61CE70;
				color: #fff;
				border: none;
				padding: 14px 32px;
				font-size: 16px;
				font-weight: 700;
				border-radius: 8px;
				cursor: pointer;
				display: inline-block;
				text-transform: uppercase;
				letter-spacing: 1px;
			}
			.rdv-btn:hover { background: #4db85c; }
			.rdv-overlay {
				display: none;
				position: fixed;
				top: 0; left: 0; right: 0; bottom: 0;
				background: rgba(0,0,0,0.6);
				z-index: 999999;
				justify-content: center;
				align-items: center;
			}
			.rdv-overlay.active { display: flex; }
			.rdv-modal {
				background: #fff;
				border-radius: 12px;
				width: min(900px, 95vw);
				max-height: 90vh;
				overflow-y: auto;
				padding: 24px;
				position: relative;
			}
			.rdv-close {
				position: absolute;
				top: 12px; right: 16px;
				font-size: 24px;
				background: none;
				border: none;
				cursor: pointer;
				color: #333;
				z-index: 10;
			}
		</style>
		<button class="rdv-btn" onclick="document.getElementById('<?php echo esc_js( $overlay_id ); ?>').classList.add('active')">
			<?php esc_html_e( 'Prendre RDV', 'mp-agenda' ); ?>
		</button>
		<div id="<?php echo esc_attr( $overlay_id ); ?>" class="rdv-overlay" onclick="if(event.target===this)this.classList.remove('active')">
			<div class="rdv-modal">
				<button class="rdv-close" onclick="document.getElementById('<?php echo esc_js( $overlay_id ); ?>').classList.remove('active')">&#10005;</button>
				<?php echo do_shortcode( '[mp_agenda_booking' . ( ! empty( $atts['default_technician'] ) ? ' default_technician="' . esc_attr( $atts['default_technician'] ) . '"' : '' ) . ']' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
