<?php
/**
 * Gère l'envoi des notifications email (confirmation client et technicien).
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Notifications.
 */
class MP_Agenda_Notifications {

	/**
	 * Enregistre le hook public permettant au client d'annuler son RDV depuis l'email.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_mp_agenda_cancel_appointment', array( $this, 'handle_public_cancellation' ) );
		add_action( 'wp_ajax_mp_agenda_cancel_appointment', array( $this, 'handle_public_cancellation' ) );
	}

	/**
	 * Envoie les emails de confirmation (client + technicien) pour un rendez-vous.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return void
	 */
	public function send_appointment_notifications( $appointment ) {
		$settings   = get_option( 'mp_agenda_settings', array() );
		$technician = MP_Agenda_DB::get_technician( $appointment['technician_id'] );

		if ( ! empty( $settings['notify_client'] ) && ! empty( $appointment['client_email'] ) ) {
			$this->send_client_email( $appointment, $technician, $settings );
		}

		if ( ! empty( $settings['notify_technician'] ) && $technician && ! empty( $technician['email'] ) ) {
			$this->send_technician_email( $appointment, $technician, $settings );
		}
	}

	/**
	 * Envoie l'email de confirmation au client.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @param array $technician  Données du technicien.
	 * @param array $settings    Réglages généraux du plugin.
	 * @return void
	 */
	private function send_client_email( $appointment, $technician, $settings ) {
		$date = new DateTime( $appointment['start_datetime'] );

		$subject = sprintf(
			/* translators: 1: nom entreprise, 2: date, 3: heure */
			__( 'Votre rendez-vous %1$s — %2$s à %3$s', 'mp-agenda' ),
			$settings['company_name'] ?? get_bloginfo( 'name' ),
			$date->format( 'd/m/Y' ),
			$date->format( 'H:i' )
		);

		$cancel_url = add_query_arg(
			array(
				'action' => 'mp_agenda_cancel_appointment',
				'token'  => $appointment['cancel_token'],
			),
			admin_url( 'admin-ajax.php' )
		);

		$body = $this->render_template(
			'confirmation-client.php',
			array(
				'appointment' => $appointment,
				'technician'  => $technician,
				'settings'    => $settings,
				'date'        => $date,
				'cancel_url'  => $cancel_url,
			)
		);

		$this->mail( $appointment['client_email'], $subject, $body, $settings );
	}

	/**
	 * Envoie l'email de notification au technicien.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @param array $technician  Données du technicien.
	 * @param array $settings    Réglages généraux du plugin.
	 * @return void
	 */
	private function send_technician_email( $appointment, $technician, $settings ) {
		$date = new DateTime( $appointment['start_datetime'] );

		$subject = sprintf(
			/* translators: 1: nom client, 2: date, 3: heure */
			__( 'Nouveau RDV — %1$s — %2$s à %3$s', 'mp-agenda' ),
			$appointment['client_name'],
			$date->format( 'd/m/Y' ),
			$date->format( 'H:i' )
		);

		$body = $this->render_template(
			'confirmation-technician.php',
			array(
				'appointment' => $appointment,
				'technician'  => $technician,
				'settings'    => $settings,
				'date'        => $date,
			)
		);

		$this->mail( $technician['email'], $subject, $body, $settings );
	}

	/**
	 * Envoie un email HTML via wp_mail().
	 *
	 * @param string $to      Destinataire.
	 * @param string $subject Objet.
	 * @param string $body    Corps HTML.
	 * @param array  $settings Réglages généraux du plugin.
	 * @return void
	 */
	private function mail( $to, $subject, $body, $settings ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( ! empty( $settings['notification_email'] ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $settings['company_name'] ?? get_bloginfo( 'name' ), $settings['notification_email'] );
		}

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Charge et rend un template email PHP avec les variables fournies.
	 *
	 * @param string $template Nom du fichier de template.
	 * @param array  $vars     Variables à extraire dans le scope du template.
	 * @return string
	 */
	private function render_template( $template, $vars ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract

		ob_start();
		require MP_AGENDA_PLUGIN_DIR . 'templates/emails/' . $template;
		return ob_get_clean();
	}

	/**
	 * Traite la demande d'annulation d'un rendez-vous depuis le lien envoyé par email.
	 *
	 * @return void
	 */
	public function handle_public_cancellation() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! $token ) {
			wp_die( esc_html__( 'Lien d\'annulation invalide.', 'mp-agenda' ) );
		}

		$appointment = MP_Agenda_DB::get_appointment_by_token( $token );

		if ( ! $appointment ) {
			wp_die( esc_html__( 'Rendez-vous introuvable ou déjà annulé.', 'mp-agenda' ) );
		}

		MP_Agenda_DB::save_appointment( array( 'status' => 'cancelled' ), $appointment['id'] );

		if ( ! empty( $appointment['google_event_id'] ) ) {
			$google_sync = new MP_Agenda_Google_Sync();
			$google_sync->delete_event( $appointment );
		}

		wp_die(
			esc_html__( 'Votre rendez-vous a bien été annulé. Nous restons à votre disposition pour en reprogrammer un autre.', 'mp-agenda' ),
			esc_html__( 'Rendez-vous annulé', 'mp-agenda' ),
			array( 'response' => 200 )
		);
	}
}
