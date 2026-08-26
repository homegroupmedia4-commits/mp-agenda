<?php
/**
 * Vue admin : gestion des services.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_services   = MP_Agenda_DB::get_services();
$mp_editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$mp_editing    = $mp_editing_id ? MP_Agenda_DB::get_service( $mp_editing_id ) : null;
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Services', 'mp-agenda' ); ?></h1>

	<?php if ( isset( $_GET['mp_agenda_notice'] ) ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Modifications enregistrées.', 'mp-agenda' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="mp-agenda-grid mp-agenda-grid-2">
		<div class="mp-agenda-card">
			<h2><?php echo $mp_editing ? esc_html__( 'Modifier le service', 'mp-agenda' ) : esc_html__( 'Ajouter un service', 'mp-agenda' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
				<?php wp_nonce_field( 'mp_agenda_save_service' ); ?>
				<input type="hidden" name="action" value="mp_agenda_save_service" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $mp_editing['id'] ?? '' ); ?>" />

				<div class="mp-agenda-field">
					<label for="mp-service-name"><?php esc_html_e( 'Nom', 'mp-agenda' ); ?> *</label>
					<input type="text" id="mp-service-name" name="name" required value="<?php echo esc_attr( $mp_editing['name'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-service-description"><?php esc_html_e( 'Description', 'mp-agenda' ); ?></label>
					<textarea id="mp-service-description" name="description" rows="3"><?php echo esc_textarea( $mp_editing['description'] ?? '' ); ?></textarea>
				</div>

				<div class="mp-agenda-field">
					<label for="mp-service-duration"><?php esc_html_e( 'Durée par défaut (minutes)', 'mp-agenda' ); ?></label>
					<input type="number" id="mp-service-duration" name="duration" min="15" step="15" value="<?php echo esc_attr( $mp_editing['duration'] ?? 60 ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-service-order"><?php esc_html_e( 'Ordre d\'affichage', 'mp-agenda' ); ?></label>
					<input type="number" id="mp-service-order" name="display_order" step="1" min="0" value="<?php echo esc_attr( $mp_editing['display_order'] ?? 0 ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label class="mp-agenda-toggle">
						<input type="checkbox" name="is_active" value="1" <?php checked( $mp_editing['is_active'] ?? 1, 1 ); ?> />
						<span><?php esc_html_e( 'Service actif', 'mp-agenda' ); ?></span>
					</label>
				</div>

				<div class="mp-agenda-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
					<?php if ( $mp_editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-services' ) ); ?>" class="button"><?php esc_html_e( 'Annuler', 'mp-agenda' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<div class="mp-agenda-card">
			<div class="mp-agenda-card-header">
				<h2><?php esc_html_e( 'Liste des services', 'mp-agenda' ); ?></h2>
			</div>

			<?php if ( ! $mp_services ) : ?>
				<p class="mp-agenda-text-secondary"><?php esc_html_e( 'Aucun service pour le moment.', 'mp-agenda' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $mp_services as $mp_service ) : ?>
				<div class="mp-agenda-showroom-row">
					<div class="mp-agenda-showroom-info">
						<div class="mp-agenda-avatar mp-agenda-avatar-placeholder"><?php echo esc_html( mb_substr( $mp_service['name'], 0, 1 ) ); ?></div>
						<div>
							<strong><?php echo esc_html( $mp_service['name'] ); ?></strong>
							<?php if ( ! $mp_service['is_active'] ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-gray"><?php esc_html_e( 'Inactif', 'mp-agenda' ); ?></span>
							<?php endif; ?>
							<div class="mp-agenda-text-secondary">
								<?php
								/* translators: %d: durée en minutes */
								echo esc_html( sprintf( __( '%d min', 'mp-agenda' ), (int) $mp_service['duration'] ) );
								?>
							</div>
						</div>
					</div>

					<div class="mp-agenda-technician-actions">
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-services&edit=' . $mp_service['id'] ) ); ?>"><?php esc_html_e( 'Modifier', 'mp-agenda' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce service ?', 'mp-agenda' ) ); ?>');">
							<?php wp_nonce_field( 'mp_agenda_delete_service' ); ?>
							<input type="hidden" name="action" value="mp_agenda_delete_service" />
							<input type="hidden" name="id" value="<?php echo esc_attr( $mp_service['id'] ); ?>" />
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'mp-agenda' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
