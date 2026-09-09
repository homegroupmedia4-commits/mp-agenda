<?php
/**
 * Vue front-end : page publique "Gérer mon rendez-vous" (shortcode [mp_agenda_manage]).
 *
 * Variables disponibles : $mp_manage_valid (bool), $mp_appointment (array|null),
 * $appointment_id (int), $token (string).
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $mp_manage_valid ) || empty( $mp_appointment ) ) {
	?>
	<div class="mp-agenda-manage">
		<div class="mp-agenda-manage-message is-error">
			<?php esc_html_e( 'Ce lien de gestion de rendez-vous est invalide ou a expiré. Merci de contacter le showroom si vous souhaitez modifier votre rendez-vous.', 'mp-agenda' ); ?>
		</div>
	</div>
	<?php
	return;
}

$mp_start        = new DateTime( $mp_appointment['start_datetime'] );
$mp_service_name = ! empty( $mp_appointment['service_name'] ) ? $mp_appointment['service_name'] : ( $mp_appointment['intervention_type'] ?? '' );
$mp_is_cancelled = ( 'cancelled' === $mp_appointment['status'] );
?>
<div class="mp-agenda-manage" id="mp-agenda-manage-root"
	data-appointment-id="<?php echo esc_attr( $appointment_id ); ?>"
	data-token="<?php echo esc_attr( $token ); ?>">

	<h2><?php esc_html_e( 'Gérer mon rendez-vous', 'mp-agenda' ); ?></h2>

	<div class="mp-agenda-manage-message is-success" id="mp-agenda-manage-cancelled-notice" <?php echo $mp_is_cancelled ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'Ce rendez-vous est annulé.', 'mp-agenda' ); ?>
	</div>

	<div class="mp-agenda-recap" id="mp-agenda-manage-recap">
		<dl>
			<dt><?php esc_html_e( 'Commercial', 'mp-agenda' ); ?></dt>
			<dd><?php echo esc_html( $mp_appointment['technician_name'] ?? '' ); ?></dd>
			<dt><?php esc_html_e( 'Service', 'mp-agenda' ); ?></dt>
			<dd><?php echo esc_html( $mp_service_name ); ?></dd>
			<dt><?php esc_html_e( 'Date et heure', 'mp-agenda' ); ?></dt>
			<dd id="mp-agenda-manage-datetime"><?php echo esc_html( $mp_start->format( 'd/m/Y' ) . ' à ' . $mp_start->format( 'H:i' ) ); ?></dd>
			<dt><?php esc_html_e( 'Adresse', 'mp-agenda' ); ?></dt>
			<dd><?php echo esc_html( $mp_appointment['client_address'] ?? '' ); ?></dd>
		</dl>
	</div>

	<div class="mp-agenda-manage-message" id="mp-agenda-manage-message" hidden></div>

	<div class="mp-agenda-manage-section" id="mp-agenda-manage-reschedule" <?php echo $mp_is_cancelled ? 'hidden' : ''; ?>>
		<h3><?php esc_html_e( 'Choisir un nouveau créneau', 'mp-agenda' ); ?></h3>

		<div class="mp-agenda-mini-calendar">
			<div class="mp-agenda-mini-cal-header">
				<button type="button" class="mp-agenda-cal-nav" id="mp-agenda-manage-cal-prev" aria-label="<?php esc_attr_e( 'Mois précédent', 'mp-agenda' ); ?>">&larr;</button>
				<span id="mp-agenda-manage-cal-label"></span>
				<button type="button" class="mp-agenda-cal-nav" id="mp-agenda-manage-cal-next" aria-label="<?php esc_attr_e( 'Mois suivant', 'mp-agenda' ); ?>">&rarr;</button>
			</div>
			<div class="mp-agenda-mini-cal-weekdays" id="mp-agenda-manage-cal-weekdays"></div>
			<div class="mp-agenda-mini-cal-days" id="mp-agenda-manage-cal-days"></div>
		</div>

		<div class="mp-agenda-slots-wrap">
			<h4 id="mp-agenda-manage-slots-title"></h4>
			<div class="mp-agenda-slots" id="mp-agenda-manage-slots"></div>
		</div>

		<div class="mp-agenda-manage-actions">
			<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" id="mp-agenda-manage-confirm" disabled><?php esc_html_e( 'Confirmer la modification', 'mp-agenda' ); ?></button>
		</div>
	</div>

	<div class="mp-agenda-manage-cancel" id="mp-agenda-manage-cancel-wrap" <?php echo $mp_is_cancelled ? 'hidden' : ''; ?>>
		<h3><?php esc_html_e( 'Annuler le rendez-vous', 'mp-agenda' ); ?></h3>
		<p class="mp-agenda-text-secondary"><?php esc_html_e( 'Vous ne pourrez pas revenir en arrière après l\'annulation.', 'mp-agenda' ); ?></p>
		<button type="button" class="mp-agenda-btn mp-agenda-btn-danger" id="mp-agenda-manage-cancel-btn"><?php esc_html_e( 'Annuler mon rendez-vous', 'mp-agenda' ); ?></button>
	</div>
</div>
