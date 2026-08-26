<?php
/**
 * Vue admin : gestion des showrooms.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_showrooms   = MP_Agenda_DB::get_showrooms();
$mp_editing_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$mp_editing     = $mp_editing_id ? MP_Agenda_DB::get_showroom( $mp_editing_id ) : null;
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Showrooms', 'mp-agenda' ); ?></h1>

	<?php if ( isset( $_GET['mp_agenda_notice'] ) ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Modifications enregistrées.', 'mp-agenda' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="mp-agenda-grid mp-agenda-grid-2">
		<div class="mp-agenda-card">
			<h2><?php echo $mp_editing ? esc_html__( 'Modifier le showroom', 'mp-agenda' ) : esc_html__( 'Ajouter un showroom', 'mp-agenda' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
				<?php wp_nonce_field( 'mp_agenda_save_showroom' ); ?>
				<input type="hidden" name="action" value="mp_agenda_save_showroom" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $mp_editing['id'] ?? '' ); ?>" />

				<div class="mp-agenda-field">
					<label for="mp-showroom-name"><?php esc_html_e( 'Nom', 'mp-agenda' ); ?> *</label>
					<input type="text" id="mp-showroom-name" name="name" required value="<?php echo esc_attr( $mp_editing['name'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-showroom-address"><?php esc_html_e( 'Adresse', 'mp-agenda' ); ?></label>
					<textarea id="mp-showroom-address" name="address" rows="2"><?php echo esc_textarea( $mp_editing['address'] ?? '' ); ?></textarea>
				</div>

				<div class="mp-agenda-field">
					<label for="mp-showroom-phone"><?php esc_html_e( 'Téléphone', 'mp-agenda' ); ?></label>
					<input type="text" id="mp-showroom-phone" name="phone" value="<?php echo esc_attr( $mp_editing['phone'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label><?php esc_html_e( 'Photo', 'mp-agenda' ); ?></label>
					<div class="mp-agenda-media-picker">
						<img id="mp-photo-preview" src="<?php echo esc_url( $mp_editing['photo_url'] ?? '' ); ?>" style="<?php echo empty( $mp_editing['photo_url'] ) ? 'display:none;' : ''; ?>" />
						<input type="hidden" id="mp-photo-url" name="photo_url" value="<?php echo esc_attr( $mp_editing['photo_url'] ?? '' ); ?>" />
						<button type="button" class="button" id="mp-photo-select"><?php esc_html_e( 'Choisir une photo', 'mp-agenda' ); ?></button>
					</div>
				</div>

				<div class="mp-agenda-field">
					<label for="mp-showroom-order"><?php esc_html_e( 'Ordre d\'affichage', 'mp-agenda' ); ?></label>
					<input type="number" id="mp-showroom-order" name="display_order" step="1" min="0" value="<?php echo esc_attr( $mp_editing['display_order'] ?? 0 ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label class="mp-agenda-toggle">
						<input type="checkbox" name="is_active" value="1" <?php checked( $mp_editing['is_active'] ?? 1, 1 ); ?> />
						<span><?php esc_html_e( 'Showroom actif', 'mp-agenda' ); ?></span>
					</label>
				</div>

				<div class="mp-agenda-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
					<?php if ( $mp_editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-showrooms' ) ); ?>" class="button"><?php esc_html_e( 'Annuler', 'mp-agenda' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<div class="mp-agenda-card">
			<div class="mp-agenda-card-header">
				<h2><?php esc_html_e( 'Liste des showrooms', 'mp-agenda' ); ?></h2>
			</div>

			<?php if ( ! $mp_showrooms ) : ?>
				<p class="mp-agenda-text-secondary"><?php esc_html_e( 'Aucun showroom pour le moment.', 'mp-agenda' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $mp_showrooms as $mp_showroom ) : ?>
				<div class="mp-agenda-showroom-row">
					<div class="mp-agenda-showroom-info">
						<?php if ( ! empty( $mp_showroom['photo_url'] ) ) : ?>
							<img class="mp-agenda-avatar" src="<?php echo esc_url( $mp_showroom['photo_url'] ); ?>" alt="" />
						<?php else : ?>
							<div class="mp-agenda-avatar mp-agenda-avatar-placeholder"><?php echo esc_html( mb_substr( $mp_showroom['name'], 0, 1 ) ); ?></div>
						<?php endif; ?>
						<div>
							<strong><?php echo esc_html( $mp_showroom['name'] ); ?></strong>
							<?php if ( ! $mp_showroom['is_active'] ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-gray"><?php esc_html_e( 'Inactif', 'mp-agenda' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $mp_showroom['address'] ) ) : ?>
								<div class="mp-agenda-text-secondary"><?php echo esc_html( $mp_showroom['address'] ); ?></div>
							<?php endif; ?>
						</div>
					</div>

					<div class="mp-agenda-technician-actions">
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-showrooms&edit=' . $mp_showroom['id'] ) ); ?>"><?php esc_html_e( 'Modifier', 'mp-agenda' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce showroom ? Les techniciens qui y sont rattachés redeviendront disponibles pour tous les showrooms.', 'mp-agenda' ) ); ?>');">
							<?php wp_nonce_field( 'mp_agenda_delete_showroom' ); ?>
							<input type="hidden" name="action" value="mp_agenda_delete_showroom" />
							<input type="hidden" name="id" value="<?php echo esc_attr( $mp_showroom['id'] ); ?>" />
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'mp-agenda' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
