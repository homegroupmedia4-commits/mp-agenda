<?php
/**
 * Vue admin : liste paginée des rendez-vous.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_technicians = MP_Agenda_DB::get_technicians();

$mp_filters = array(
	'from'          => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
	'to'            => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
	'technician_id' => isset( $_GET['technician_id'] ) ? absint( $_GET['technician_id'] ) : 0,
	'status'        => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
	'search'        => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
);

$mp_page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$mp_per_page = 20;

$mp_total = MP_Agenda_DB::count_appointments( $mp_filters );
$mp_list  = MP_Agenda_DB::get_appointments(
	array_merge(
		$mp_filters,
		array(
			'page'     => $mp_page,
			'per_page' => $mp_per_page,
			'orderby'  => 'start_datetime',
			'order'    => 'DESC',
		)
	)
);

$mp_status_labels = array(
	'pending'   => __( 'En attente', 'mp-agenda' ),
	'confirmed' => __( 'Confirmé', 'mp-agenda' ),
	'cancelled' => __( 'Annulé', 'mp-agenda' ),
	'completed' => __( 'Terminé', 'mp-agenda' ),
);

$mp_source_labels = array(
	'online' => __( 'En ligne', 'mp-agenda' ),
	'admin'  => __( 'Admin', 'mp-agenda' ),
	'google' => __( 'Google', 'mp-agenda' ),
);

$mp_export_args = array_merge( array( 'action' => 'mp_agenda_export_csv' ), array_filter( $mp_filters ) );
$mp_export_url  = wp_nonce_url( add_query_arg( $mp_export_args, admin_url( 'admin-post.php' ) ), 'mp_agenda_export_csv' );

$mp_total_pages = (int) ceil( $mp_total / $mp_per_page );
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title">
		<?php esc_html_e( 'Rendez-vous', 'mp-agenda' ); ?>
		<a href="<?php echo esc_url( $mp_export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Exporter CSV', 'mp-agenda' ); ?></a>
	</h1>

	<div class="mp-agenda-card">
		<form method="get" class="mp-agenda-filters-bar">
			<input type="hidden" name="page" value="mp-agenda-appointments" />

			<input type="text" name="search" placeholder="<?php esc_attr_e( 'Nom ou téléphone…', 'mp-agenda' ); ?>" value="<?php echo esc_attr( $mp_filters['search'] ); ?>" />

			<select name="technician_id">
				<option value=""><?php esc_html_e( 'Tous les techniciens', 'mp-agenda' ); ?></option>
				<?php foreach ( $mp_technicians as $mp_tech ) : ?>
					<option value="<?php echo esc_attr( $mp_tech['id'] ); ?>" <?php selected( $mp_filters['technician_id'], $mp_tech['id'] ); ?>><?php echo esc_html( $mp_tech['name'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="status">
				<option value=""><?php esc_html_e( 'Tous les statuts', 'mp-agenda' ); ?></option>
				<?php foreach ( $mp_status_labels as $mp_key => $mp_label ) : ?>
					<option value="<?php echo esc_attr( $mp_key ); ?>" <?php selected( $mp_filters['status'], $mp_key ); ?>><?php echo esc_html( $mp_label ); ?></option>
				<?php endforeach; ?>
			</select>

			<input type="date" name="from" value="<?php echo esc_attr( $mp_filters['from'] ); ?>" />
			<input type="date" name="to" value="<?php echo esc_attr( $mp_filters['to'] ); ?>" />

			<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'mp-agenda' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-appointments' ) ); ?>" class="button-link"><?php esc_html_e( 'Réinitialiser', 'mp-agenda' ); ?></a>
		</form>

		<table class="widefat mp-agenda-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date & heure', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Technicien', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Client', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Type', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Source', 'mp-agenda' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'mp-agenda' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $mp_list ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Aucun rendez-vous trouvé.', 'mp-agenda' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $mp_list as $mp_appt ) :
					$mp_date = new DateTime( $mp_appt['start_datetime'] );
					?>
					<tr>
						<td><?php echo esc_html( $mp_date->format( 'd/m/Y' ) . ' à ' . $mp_date->format( 'H:i' ) ); ?></td>
						<td><?php echo esc_html( $mp_appt['technician_name'] ); ?></td>
						<td><?php echo esc_html( $mp_appt['client_name'] ); ?><br /><span class="mp-agenda-text-secondary"><?php echo esc_html( $mp_appt['client_phone'] ); ?></span></td>
						<td><?php echo esc_html( $mp_appt['intervention_type'] ); ?></td>
						<td><span class="mp-agenda-badge mp-agenda-badge-<?php echo esc_attr( $mp_appt['status'] ); ?>"><?php echo esc_html( $mp_status_labels[ $mp_appt['status'] ] ?? $mp_appt['status'] ); ?></span></td>
						<td><?php echo esc_html( $mp_source_labels[ $mp_appt['source'] ] ?? $mp_appt['source'] ); ?></td>
						<td>
							<button type="button" class="button mp-agenda-edit-appointment" data-id="<?php echo esc_attr( $mp_appt['id'] ); ?>"><?php esc_html_e( 'Modifier', 'mp-agenda' ); ?></button>
							<button type="button" class="button button-link-delete mp-agenda-delete-appointment" data-id="<?php echo esc_attr( $mp_appt['id'] ); ?>"><?php esc_html_e( 'Supprimer', 'mp-agenda' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $mp_total_pages > 1 ) : ?>
			<div class="mp-agenda-pagination">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $mp_page,
						'total'     => $mp_total_pages,
						'prev_text' => __( '&laquo; Précédent', 'mp-agenda' ),
						'next_text' => __( 'Suivant &raquo;', 'mp-agenda' ),
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>

	<?php require MP_AGENDA_PLUGIN_DIR . 'admin/views/appointment-modal.php'; ?>
</div>
