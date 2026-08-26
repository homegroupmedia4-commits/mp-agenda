<?php
/**
 * Vue admin : réglages généraux du plugin.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_settings                 = get_option( 'mp_agenda_settings', array() );
$mp_delete_data_on_uninstall = get_option( 'mp_agenda_delete_data_on_uninstall', false );
$mp_gdpr_text                = get_option( 'mp_agenda_gdpr_text', '' );
$mp_gdpr_retention           = get_option( 'mp_agenda_gdpr_retention_months', 24 );
$mp_google_client_id         = get_option( 'mp_agenda_google_client_id', '' );
$mp_google_secret            = get_option( 'mp_agenda_google_client_secret', '' );
$mp_callback_url             = admin_url( 'admin-ajax.php?action=mp_agenda_google_callback' );
$mp_active_tab               = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Réglages MP Agenda', 'mp-agenda' ); ?></h1>

	<?php if ( isset( $_GET['mp_agenda_notice'] ) ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Réglages enregistrés.', 'mp-agenda' ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="?page=mp-agenda-settings&tab=general" class="nav-tab <?php echo 'general' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Général', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=google" class="nav-tab <?php echo 'google' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Google API', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=notifications" class="nav-tab <?php echo 'notifications' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=gdpr" class="nav-tab <?php echo 'gdpr' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'RGPD', 'mp-agenda' ); ?></a>
	</h2>

	<div class="mp-agenda-card">

	<?php if ( 'general' === $mp_active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_settings' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_settings" />
			<input type="hidden" name="mp_agenda_general_tab" value="1" />

			<div class="mp-agenda-field">
				<label for="company_name"><?php esc_html_e( 'Nom de l\'entreprise', 'mp-agenda' ); ?></label>
				<input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $mp_settings['company_name'] ?? '' ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label for="notification_email"><?php esc_html_e( 'Email d\'envoi des notifications', 'mp-agenda' ); ?></label>
				<input type="email" id="notification_email" name="notification_email" value="<?php echo esc_attr( $mp_settings['notification_email'] ?? '' ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label for="default_duration"><?php esc_html_e( 'Durée de rendez-vous par défaut (minutes)', 'mp-agenda' ); ?></label>
				<input type="number" id="default_duration" name="default_duration" min="15" step="15" value="<?php echo esc_attr( $mp_settings['default_duration'] ?? 60 ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label for="timezone"><?php esc_html_e( 'Fuseau horaire', 'mp-agenda' ); ?></label>
				<input type="text" id="timezone" name="timezone" value="<?php echo esc_attr( $mp_settings['timezone'] ?? wp_timezone_string() ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="require_technician_choice" value="1" <?php checked( ! empty( $mp_settings['require_technician_choice'] ) ); ?> />
					<span><?php esc_html_e( 'Rendre le choix du commercial obligatoire dans le formulaire client', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<hr class="mp-agenda-separator" />

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $mp_delete_data_on_uninstall ) ); ?> />
					<span><?php esc_html_e( 'Supprimer toutes les données à la désinstallation', 'mp-agenda' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Si décochée (recommandé), les rendez-vous, techniciens, showrooms, services et réglages sont conservés en base même après suppression du plugin.', 'mp-agenda' ); ?></p>
			</div>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
			</div>
		</form>

	<?php elseif ( 'google' === $mp_active_tab ) : ?>
		<div class="mp-agenda-google-guide">
			<h3><?php esc_html_e( 'Configurer la synchronisation Google Agenda', 'mp-agenda' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Rendez-vous sur la Google Cloud Console et créez un nouveau projet.', 'mp-agenda' ); ?></li>
				<li><?php esc_html_e( 'Dans "API et services", activez l\'API "Google Calendar API".', 'mp-agenda' ); ?></li>
				<li><?php esc_html_e( 'Configurez l\'écran de consentement OAuth (type externe, ajoutez votre email en tant qu\'utilisateur de test si l\'app n\'est pas publiée).', 'mp-agenda' ); ?></li>
				<li><?php esc_html_e( 'Créez un identifiant OAuth 2.0 de type "Application Web".', 'mp-agenda' ); ?></li>
				<li><?php esc_html_e( 'Ajoutez l\'URI de redirection autorisée suivante :', 'mp-agenda' ); ?>
					<code><?php echo esc_html( $mp_callback_url ); ?></code>
				</li>
				<li><?php esc_html_e( 'Copiez le Client ID et le Client Secret ci-dessous.', 'mp-agenda' ); ?></li>
			</ol>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_google_credentials' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_google_credentials" />

			<div class="mp-agenda-field">
				<label for="google_client_id"><?php esc_html_e( 'Client ID', 'mp-agenda' ); ?></label>
				<input type="text" id="google_client_id" name="google_client_id" value="<?php echo esc_attr( $mp_google_client_id ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label for="google_client_secret"><?php esc_html_e( 'Client Secret', 'mp-agenda' ); ?></label>
				<input type="password" id="google_client_secret" name="google_client_secret" value="<?php echo esc_attr( $mp_google_secret ); ?>" autocomplete="off" />
			</div>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
			</div>
		</form>

	<?php elseif ( 'notifications' === $mp_active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_settings' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_settings" />
			<input type="hidden" name="company_name" value="<?php echo esc_attr( $mp_settings['company_name'] ?? '' ); ?>" />
			<input type="hidden" name="notification_email" value="<?php echo esc_attr( $mp_settings['notification_email'] ?? '' ); ?>" />
			<input type="hidden" name="default_duration" value="<?php echo esc_attr( $mp_settings['default_duration'] ?? 60 ); ?>" />
			<input type="hidden" name="timezone" value="<?php echo esc_attr( $mp_settings['timezone'] ?? '' ); ?>" />

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="notify_client" value="1" <?php checked( ! empty( $mp_settings['notify_client'] ) ); ?> />
					<span><?php esc_html_e( 'Envoyer un email de confirmation au client', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="notify_technician" value="1" <?php checked( ! empty( $mp_settings['notify_technician'] ) ); ?> />
					<span><?php esc_html_e( 'Envoyer un email de notification au commercial', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<p class="description"><?php esc_html_e( 'Les modèles d\'emails se trouvent dans /templates/emails/ et peuvent être personnalisés par un développeur.', 'mp-agenda' ); ?></p>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
			</div>
		</form>

	<?php elseif ( 'gdpr' === $mp_active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_settings' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_settings" />
			<input type="hidden" name="company_name" value="<?php echo esc_attr( $mp_settings['company_name'] ?? '' ); ?>" />
			<input type="hidden" name="notification_email" value="<?php echo esc_attr( $mp_settings['notification_email'] ?? '' ); ?>" />
			<input type="hidden" name="default_duration" value="<?php echo esc_attr( $mp_settings['default_duration'] ?? 60 ); ?>" />
			<input type="hidden" name="timezone" value="<?php echo esc_attr( $mp_settings['timezone'] ?? '' ); ?>" />
			<input type="hidden" name="notify_client" value="<?php echo ! empty( $mp_settings['notify_client'] ) ? '1' : '0'; ?>" />
			<input type="hidden" name="notify_technician" value="<?php echo ! empty( $mp_settings['notify_technician'] ) ? '1' : '0'; ?>" />

			<div class="mp-agenda-field">
				<label for="gdpr_text"><?php esc_html_e( 'Mention légale RGPD (affichée sous le formulaire client)', 'mp-agenda' ); ?></label>
				<textarea id="gdpr_text" name="gdpr_text" rows="5"><?php echo esc_textarea( $mp_gdpr_text ); ?></textarea>
			</div>

			<div class="mp-agenda-field">
				<label for="gdpr_retention_months"><?php esc_html_e( 'Durée de conservation des données (mois)', 'mp-agenda' ); ?></label>
				<input type="number" id="gdpr_retention_months" name="gdpr_retention_months" min="1" value="<?php echo esc_attr( $mp_gdpr_retention ); ?>" />
			</div>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
			</div>
		</form>
	<?php endif; ?>

	</div>
</div>
