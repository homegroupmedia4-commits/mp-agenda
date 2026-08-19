<?php
/**
 * Vue admin : modal de création/édition d'un rendez-vous.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_technicians         = MP_Agenda_DB::get_technicians( true );
$mp_intervention_types  = get_option( 'mp_agenda_intervention_types', array() );

$mp_duration_options = array(
	30  => __( '30 min', 'mp-agenda' ),
	60  => __( '1h', 'mp-agenda' ),
	90  => __( '1h30', 'mp-agenda' ),
	120 => __( '2h', 'mp-agenda' ),
	150 => __( '2h30', 'mp-agenda' ),
	180 => __( '3h', 'mp-agenda' ),
	240 => __( 'Demi-journée (4h)', 'mp-agenda' ),
	480 => __( 'Journée complète (8h)', 'mp-agenda' ),
);
?>
<div id="mp-agenda-modal-overlay" class="mp-agenda-modal-overlay" hidden>
	<div class="mp-agenda-modal" role="dialog" aria-modal="true" aria-labelledby="mp-agenda-modal-title">
		<div class="mp-agenda-modal-header">
			<h2 id="mp-agenda-modal-title"><?php esc_html_e( 'Nouveau rendez-vous', 'mp-agenda' ); ?></h2>
			<button type="button" class="mp-agenda-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'mp-agenda' ); ?>">&times;</button>
		</div>

		<form id="mp-agenda-appointment-form" class="mp-agenda-form">
			<input type="hidden" id="mp-appt-id" name="id" value="" />

			<div class="mp-agenda-field">
				<label for="mp-appt-technician"><?php esc_html_e( 'Technicien', 'mp-agenda' ); ?> *</label>
				<select id="mp-appt-technician" name="technician_id" required>
					<?php foreach ( $mp_technicians as $mp_tech ) : ?>
						<option value="<?php echo esc_attr( $mp_tech['id'] ); ?>"><?php echo esc_html( $mp_tech['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mp-agenda-field-row">
				<div class="mp-agenda-field">
					<label for="mp-appt-date"><?php esc_html_e( 'Date', 'mp-agenda' ); ?> *</label>
					<input type="date" id="mp-appt-date" name="date" required />
				</div>
				<div class="mp-agenda-field">
					<label for="mp-appt-time"><?php esc_html_e( 'Heure de début', 'mp-agenda' ); ?> *</label>
					<select id="mp-appt-time" name="time" required></select>
				</div>
				<div class="mp-agenda-field">
					<label for="mp-appt-duration"><?php esc_html_e( 'Durée', 'mp-agenda' ); ?> *</label>
					<select id="mp-appt-duration" name="duration" required>
						<?php foreach ( $mp_duration_options as $mp_minutes => $mp_label ) : ?>
							<option value="<?php echo esc_attr( $mp_minutes ); ?>" <?php selected( 60, $mp_minutes ); ?>><?php echo esc_html( $mp_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<hr class="mp-agenda-separator" />
			<h3><?php esc_html_e( 'Informations client', 'mp-agenda' ); ?></h3>

			<div class="mp-agenda-field-row">
				<div class="mp-agenda-field">
					<label for="mp-appt-client-name"><?php esc_html_e( 'Nom du client', 'mp-agenda' ); ?> *</label>
					<input type="text" id="mp-appt-client-name" name="client_name" required />
				</div>
				<div class="mp-agenda-field">
					<label for="mp-appt-client-phone"><?php esc_html_e( 'Téléphone', 'mp-agenda' ); ?> *</label>
					<input type="text" id="mp-appt-client-phone" name="client_phone" required />
				</div>
			</div>

			<div class="mp-agenda-field">
				<label for="mp-appt-client-email"><?php esc_html_e( 'Email', 'mp-agenda' ); ?></label>
				<input type="email" id="mp-appt-client-email" name="client_email" />
			</div>

			<div class="mp-agenda-field">
				<label for="mp-appt-client-address"><?php esc_html_e( 'Adresse du chantier', 'mp-agenda' ); ?></label>
				<textarea id="mp-appt-client-address" name="client_address" rows="2"></textarea>
			</div>

			<hr class="mp-agenda-separator" />
			<h3><?php esc_html_e( 'Détails intervention', 'mp-agenda' ); ?></h3>

			<div class="mp-agenda-field-row">
				<div class="mp-agenda-field">
					<label for="mp-appt-type"><?php esc_html_e( 'Type d\'intervention', 'mp-agenda' ); ?></label>
					<select id="mp-appt-type" name="intervention_type">
						<?php foreach ( $mp_intervention_types as $mp_type ) : ?>
							<option value="<?php echo esc_attr( $mp_type ); ?>"><?php echo esc_html( $mp_type ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mp-agenda-field">
					<label for="mp-appt-surface"><?php esc_html_e( 'Surface estimée', 'mp-agenda' ); ?></label>
					<input type="text" id="mp-appt-surface" name="surface" placeholder="<?php esc_attr_e( 'Ex : 45 m²', 'mp-agenda' ); ?>" />
				</div>
			</div>

			<div class="mp-agenda-field">
				<label><?php esc_html_e( 'Urgence', 'mp-agenda' ); ?></label>
				<div class="mp-agenda-radio-group">
					<label><input type="radio" name="urgency" value="normal" checked /> <?php esc_html_e( 'Normal', 'mp-agenda' ); ?></label>
					<label><input type="radio" name="urgency" value="urgent" /> <?php esc_html_e( 'Urgent', 'mp-agenda' ); ?></label>
				</div>
			</div>

			<div class="mp-agenda-field">
				<label for="mp-appt-notes"><?php esc_html_e( 'Notes internes', 'mp-agenda' ); ?></label>
				<textarea id="mp-appt-notes" name="internal_notes" rows="3" placeholder="<?php esc_attr_e( 'Visibles uniquement par l\'équipe MP Rénov', 'mp-agenda' ); ?>"></textarea>
			</div>

			<div class="mp-agenda-field mp-agenda-status-field" hidden>
				<label for="mp-appt-status"><?php esc_html_e( 'Statut', 'mp-agenda' ); ?></label>
				<select id="mp-appt-status" name="status">
					<option value="pending"><?php esc_html_e( 'En attente', 'mp-agenda' ); ?></option>
					<option value="confirmed"><?php esc_html_e( 'Confirmé', 'mp-agenda' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Annulé', 'mp-agenda' ); ?></option>
					<option value="completed"><?php esc_html_e( 'Terminé', 'mp-agenda' ); ?></option>
				</select>
			</div>

			<div class="mp-agenda-modal-error" hidden></div>

			<div class="mp-agenda-modal-footer">
				<button type="button" class="button button-link-delete mp-agenda-delete-btn" hidden><?php esc_html_e( 'Supprimer', 'mp-agenda' ); ?></button>
				<div class="mp-agenda-modal-footer-right">
					<button type="button" class="button mp-agenda-modal-cancel"><?php esc_html_e( 'Annuler', 'mp-agenda' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
				</div>
			</div>
		</form>
	</div>
</div>
