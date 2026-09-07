<?php
/**
 * Vue admin : gestion des techniciens.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_technicians       = MP_Agenda_DB::get_technicians();
$mp_showrooms         = MP_Agenda_DB::get_showrooms( true );
$mp_editing_id        = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$mp_editing           = $mp_editing_id ? MP_Agenda_DB::get_technician( $mp_editing_id ) : null;
$mp_google_ready      = get_option( 'mp_agenda_google_client_id' ) && get_option( 'mp_agenda_google_client_secret' );
$mp_google_sync_mode  = get_option( 'mp_agenda_google_sync_mode', 'individual' );
$mp_is_shared_mode    = 'shared' === $mp_google_sync_mode;
$mp_shared_email      = get_option( 'mp_agenda_shared_google_email', '' );
$mp_shared_connected  = (bool) get_option( 'mp_agenda_shared_google_refresh_token' );
$mp_shared_expires_at = get_option( 'mp_agenda_shared_google_token_expires_at' );
$mp_shared_expires_ts = $mp_shared_expires_at ? strtotime( $mp_shared_expires_at ) : 0;
$mp_shared_valid      = $mp_shared_connected && $mp_shared_expires_ts > time();

$mp_days = array(
	'mon' => __( 'Lundi', 'mp-agenda' ),
	'tue' => __( 'Mardi', 'mp-agenda' ),
	'wed' => __( 'Mercredi', 'mp-agenda' ),
	'thu' => __( 'Jeudi', 'mp-agenda' ),
	'fri' => __( 'Vendredi', 'mp-agenda' ),
	'sat' => __( 'Samedi', 'mp-agenda' ),
	'sun' => __( 'Dimanche', 'mp-agenda' ),
);

$mp_hours = array();
if ( $mp_editing && ! empty( $mp_editing['working_hours'] ) ) {
	$mp_hours = json_decode( $mp_editing['working_hours'], true );
}

$mp_has_connected_technician = false;
foreach ( $mp_technicians as $mp_tech_check ) {
	if ( ! empty( $mp_tech_check['google_refresh_token'] ) ) {
		$mp_has_connected_technician = true;
		break;
	}
}

// Le bouton "Forcer la synchronisation" n'a de sens que si le jeu de tokens
// réellement utilisé par le mode actif (individuel ou partagé) est connecté.
$mp_show_sync_button = $mp_is_shared_mode ? $mp_shared_connected : $mp_has_connected_technician;
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Commerciaux', 'mp-agenda' ); ?></h1>

	<?php if ( isset( $_GET['mp_agenda_notice'] ) ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Modifications enregistrées.', 'mp-agenda' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="mp-agenda-grid mp-agenda-grid-2">
		<div class="mp-agenda-card">
			<h2><?php echo $mp_editing ? esc_html__( 'Modifier le commercial', 'mp-agenda' ) : esc_html__( 'Ajouter un commercial', 'mp-agenda' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
				<?php wp_nonce_field( 'mp_agenda_save_technician' ); ?>
				<input type="hidden" name="action" value="mp_agenda_save_technician" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $mp_editing['id'] ?? '' ); ?>" />

				<div class="mp-agenda-field">
					<label for="mp-name"><?php esc_html_e( 'Nom', 'mp-agenda' ); ?> *</label>
					<input type="text" id="mp-name" name="name" required value="<?php echo esc_attr( $mp_editing['name'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-email"><?php esc_html_e( 'Email', 'mp-agenda' ); ?> *</label>
					<input type="email" id="mp-email" name="email" required value="<?php echo esc_attr( $mp_editing['email'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-phone"><?php esc_html_e( 'Téléphone', 'mp-agenda' ); ?></label>
					<input type="text" id="mp-phone" name="phone" value="<?php echo esc_attr( $mp_editing['phone'] ?? '' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-zone"><?php esc_html_e( 'Zone d\'intervention', 'mp-agenda' ); ?></label>
					<input type="text" id="mp-zone" name="zone" value="<?php echo esc_attr( $mp_editing['zone'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ex : Paris et petite couronne', 'mp-agenda' ); ?>" />
				</div>

				<div class="mp-agenda-field">
					<label for="mp-showroom"><?php esc_html_e( 'Showroom', 'mp-agenda' ); ?></label>
					<select id="mp-showroom" name="showroom_id">
						<option value=""><?php esc_html_e( 'Aucun (tous les showrooms)', 'mp-agenda' ); ?></option>
						<?php foreach ( $mp_showrooms as $mp_showroom ) : ?>
							<option value="<?php echo esc_attr( $mp_showroom['id'] ); ?>" <?php selected( $mp_editing['showroom_id'] ?? '', $mp_showroom['id'] ); ?>><?php echo esc_html( $mp_showroom['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
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
					<label class="mp-agenda-toggle">
						<input type="checkbox" name="is_active" value="1" <?php checked( $mp_editing['is_active'] ?? 1, 1 ); ?> />
						<span><?php esc_html_e( 'Commercial actif', 'mp-agenda' ); ?></span>
					</label>
				</div>

				<h3><?php esc_html_e( 'Horaires de travail', 'mp-agenda' ); ?></h3>
				<div class="mp-agenda-hours">
					<?php foreach ( $mp_days as $mp_key => $mp_label ) :
						$mp_day       = $mp_hours[ $mp_key ] ?? array( 'active' => in_array( $mp_key, array( 'mon', 'tue', 'wed', 'thu', 'fri' ), true ), 'start' => '08:00', 'end' => '18:00' );
						?>
						<div class="mp-agenda-hours-row">
							<label class="mp-agenda-toggle mp-agenda-hours-day">
								<input type="checkbox" name="active_<?php echo esc_attr( $mp_key ); ?>" value="1" <?php checked( ! empty( $mp_day['active'] ) ); ?> />
								<span><?php echo esc_html( $mp_label ); ?></span>
							</label>
							<input type="time" name="start_<?php echo esc_attr( $mp_key ); ?>" value="<?php echo esc_attr( $mp_day['start'] ); ?>" />
							<span class="mp-agenda-hours-sep">—</span>
							<input type="time" name="end_<?php echo esc_attr( $mp_key ); ?>" value="<?php echo esc_attr( $mp_day['end'] ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>

				<div class="mp-agenda-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
					<?php if ( $mp_editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-technicians' ) ); ?>" class="button"><?php esc_html_e( 'Annuler', 'mp-agenda' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<div class="mp-agenda-card">
			<div class="mp-agenda-card-header">
				<h2><?php esc_html_e( 'Liste des commerciaux', 'mp-agenda' ); ?></h2>
				<?php if ( $mp_show_sync_button ) : ?>
					<span>
						<button type="button" class="button mp-agenda-sync-google-btn">🔄 <?php esc_html_e( 'Forcer la synchronisation Google', 'mp-agenda' ); ?></button>
						<span class="mp-agenda-sync-status"></span>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( $mp_show_sync_button ) : ?>
				<p class="mp-agenda-text-secondary"><?php esc_html_e( 'La synchronisation automatique tourne toutes les 5 minutes via le cron WordPress. Sur certains hébergements, ce cron peut être retardé ou inactif : utilisez ce bouton pour forcer immédiatement la récupération des événements Google Agenda (RDV créés côté plugin ET créneaux bloqués créés directement dans Google Agenda).', 'mp-agenda' ); ?></p>
			<?php endif; ?>

			<?php if ( $mp_is_shared_mode ) : ?>
				<div class="mp-agenda-shared-google-block">
					<strong><?php esc_html_e( 'Agenda Google partagé', 'mp-agenda' ); ?></strong>

					<?php if ( $mp_shared_connected && $mp_shared_valid ) : ?>
						<div class="mp-agenda-shared-google-row">
							<span class="mp-agenda-badge mp-agenda-badge-success">
								✅ <?php echo esc_html( sprintf( __( 'Agenda partagé connecté à %s', 'mp-agenda' ), $mp_shared_email ? $mp_shared_email : __( '(email indisponible)', 'mp-agenda' ) ) ); ?>
							</span>
							<span class="mp-agenda-text-secondary"><?php echo esc_html( sprintf( __( 'Token valide jusqu\'au %s', 'mp-agenda' ), wp_date( 'd/m/Y H:i', $mp_shared_expires_ts ) ) ); ?></span>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'mp_agenda_google_disconnect_shared' ); ?>
								<input type="hidden" name="action" value="mp_agenda_google_disconnect_shared" />
								<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Déconnecter', 'mp-agenda' ); ?></button>
							</form>
						</div>
					<?php elseif ( $mp_shared_connected && ! $mp_shared_valid ) : ?>
						<div class="mp-agenda-shared-google-row">
							<span class="mp-agenda-badge mp-agenda-badge-warning">⚠️ <?php esc_html_e( 'Token expiré — Renouvellement automatique au prochain appel', 'mp-agenda' ); ?></span>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'mp_agenda_google_disconnect_shared' ); ?>
								<input type="hidden" name="action" value="mp_agenda_google_disconnect_shared" />
								<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Déconnecter', 'mp-agenda' ); ?></button>
							</form>
						</div>
					<?php elseif ( $mp_google_ready ) : ?>
						<div class="mp-agenda-shared-google-row">
							<span class="mp-agenda-text-secondary"><?php esc_html_e( 'Connectez l\'agenda partagé.', 'mp-agenda' ); ?></span>
							<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=mp_agenda_google_connect&shared=1' ), 'mp_agenda_google_connect' ) ); ?>">
								<?php esc_html_e( 'Connecter l\'agenda partagé', 'mp-agenda' ); ?>
							</a>
						</div>
					<?php else : ?>
						<p class="mp-agenda-text-secondary"><?php esc_html_e( 'Configurez d\'abord l\'API Google dans les Réglages.', 'mp-agenda' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $mp_technicians as $mp_tech ) : ?>
				<div class="mp-agenda-technician-row">
					<div class="mp-agenda-technician-info">
						<?php if ( ! empty( $mp_tech['photo_url'] ) ) : ?>
							<img class="mp-agenda-avatar" src="<?php echo esc_url( $mp_tech['photo_url'] ); ?>" alt="" />
						<?php else : ?>
							<div class="mp-agenda-avatar mp-agenda-avatar-placeholder"><?php echo esc_html( mb_substr( $mp_tech['name'], 0, 1 ) ); ?></div>
						<?php endif; ?>
						<div>
							<strong><?php echo esc_html( $mp_tech['name'] ); ?></strong>
							<?php if ( ! $mp_tech['is_active'] ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-gray"><?php esc_html_e( 'Inactif', 'mp-agenda' ); ?></span>
							<?php endif; ?>
							<div class="mp-agenda-text-secondary"><?php echo esc_html( $mp_tech['email'] ); ?></div>
						</div>
					</div>

					<?php if ( $mp_is_shared_mode ) : ?>
						<div class="mp-agenda-technician-google">
							<span class="mp-agenda-text-secondary"><?php esc_html_e( 'Géré via l\'agenda Google partagé (voir ci-dessus).', 'mp-agenda' ); ?></span>
						</div>
					<?php else : ?>
						<?php
						$mp_tech_has_refresh = ! empty( $mp_tech['google_refresh_token'] );
						$mp_tech_expires_ts  = ! empty( $mp_tech['google_token_expires_at'] ) ? strtotime( $mp_tech['google_token_expires_at'] ) : 0;
						$mp_tech_token_valid = $mp_tech_expires_ts > time();
						?>
						<div class="mp-agenda-technician-google">
							<?php if ( $mp_tech_has_refresh && $mp_tech_token_valid ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-success">
									✅ <?php echo esc_html( sprintf( __( 'Connecté — Token valide jusqu\'au %s', 'mp-agenda' ), wp_date( 'd/m/Y H:i', $mp_tech_expires_ts ) ) ); ?>
								</span>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'mp_agenda_google_disconnect' ); ?>
									<input type="hidden" name="action" value="mp_agenda_google_disconnect" />
									<input type="hidden" name="technician_id" value="<?php echo esc_attr( $mp_tech['id'] ); ?>" />
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Déconnecter', 'mp-agenda' ); ?></button>
								</form>
							<?php elseif ( $mp_tech_has_refresh && ! $mp_tech_token_valid ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-warning">
									⚠️ <?php esc_html_e( 'Token expiré — Renouvellement automatique au prochain appel', 'mp-agenda' ); ?>
								</span>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'mp_agenda_google_disconnect' ); ?>
									<input type="hidden" name="action" value="mp_agenda_google_disconnect" />
									<input type="hidden" name="technician_id" value="<?php echo esc_attr( $mp_tech['id'] ); ?>" />
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Déconnecter', 'mp-agenda' ); ?></button>
								</form>
							<?php elseif ( $mp_google_ready ) : ?>
								<span class="mp-agenda-badge mp-agenda-badge-danger">❌ <?php esc_html_e( 'Déconnecté — Reconnexion nécessaire', 'mp-agenda' ); ?></span>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=mp_agenda_google_connect&technician_id=' . $mp_tech['id'] ), 'mp_agenda_google_connect' ) ); ?>">
									<?php esc_html_e( 'Connecter Google Agenda', 'mp-agenda' ); ?>
								</a>
							<?php else : ?>
								<span class="mp-agenda-text-secondary"><?php esc_html_e( 'Configurez d\'abord l\'API Google dans les Réglages.', 'mp-agenda' ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="mp-agenda-technician-actions">
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mp-agenda-technicians&edit=' . $mp_tech['id'] ) ); ?>"><?php esc_html_e( 'Modifier', 'mp-agenda' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce commercial et tous ses rendez-vous ?', 'mp-agenda' ) ); ?>');">
							<?php wp_nonce_field( 'mp_agenda_delete_technician' ); ?>
							<input type="hidden" name="action" value="mp_agenda_delete_technician" />
							<input type="hidden" name="id" value="<?php echo esc_attr( $mp_tech['id'] ); ?>" />
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Supprimer', 'mp-agenda' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
