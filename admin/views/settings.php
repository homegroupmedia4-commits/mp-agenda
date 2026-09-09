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
$mp_google_sync_mode         = get_option( 'mp_agenda_google_sync_mode', 'individual' );
$mp_sms                      = MP_Agenda_SMS::get_settings();
$mp_active_tab               = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
?>
<div class="wrap mp-agenda-wrap">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Réglages MP Agenda', 'mp-agenda' ); ?></h1>

	<?php
	$mp_notice      = isset( $_GET['mp_agenda_notice'] ) ? sanitize_key( wp_unslash( $_GET['mp_agenda_notice'] ) ) : '';
	$mp_sms_result  = '';
	if ( in_array( $mp_notice, array( 'test_sms_sent', 'test_sms_failed' ), true ) ) {
		$mp_sms_result = (string) get_transient( 'mp_agenda_sms_test_result_' . get_current_user_id() );
		delete_transient( 'mp_agenda_sms_test_result_' . get_current_user_id() );
	}
	if ( 'test_reminder_sent' === $mp_notice ) :
		?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Email de rappel test envoyé.', 'mp-agenda' ); ?></p>
		</div>
	<?php elseif ( 'test_reminder_failed' === $mp_notice ) : ?>
		<div class="notice notice-error is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Impossible d\'envoyer l\'email de rappel test (adresse email invalide).', 'mp-agenda' ); ?></p>
		</div>
	<?php elseif ( 'test_sms_sent' === $mp_notice ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php echo esc_html( '' !== $mp_sms_result ? $mp_sms_result : __( 'SMS de test transmis à OVH.', 'mp-agenda' ) ); ?> <?php esc_html_e( 'Vérifiez la réception et les logs si besoin.', 'mp-agenda' ); ?></p>
		</div>
	<?php elseif ( 'test_sms_failed' === $mp_notice ) : ?>
		<div class="notice notice-error is-dismissible mp-agenda-notice">
			<p>
				<strong><?php esc_html_e( 'Échec de l\'envoi du SMS de test.', 'mp-agenda' ); ?></strong><br />
				<?php echo esc_html( '' !== $mp_sms_result ? $mp_sms_result : __( 'Cause inconnue — voir error_log.', 'mp-agenda' ) ); ?>
			</p>
		</div>
	<?php elseif ( '' !== $mp_notice ) : ?>
		<div class="notice notice-success is-dismissible mp-agenda-notice">
			<p><?php esc_html_e( 'Réglages enregistrés.', 'mp-agenda' ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="?page=mp-agenda-settings&tab=general" class="nav-tab <?php echo 'general' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Général', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=google" class="nav-tab <?php echo 'google' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Google API', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=notifications" class="nav-tab <?php echo 'notifications' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'mp-agenda' ); ?></a>
		<a href="?page=mp-agenda-settings&tab=sms" class="nav-tab <?php echo 'sms' === $mp_active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'SMS', 'mp-agenda' ); ?></a>
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
				<li><?php esc_html_e( 'Ajoutez l\'URI de redirection autorisée indiquée dans la documentation.', 'mp-agenda' ); ?></li>
				<li><?php esc_html_e( 'Copiez le Client ID et le Client Secret ci-dessous.', 'mp-agenda' ); ?></li>
			</ol>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_google_credentials' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_google_credentials" />

			<div class="mp-agenda-field">
				<label><?php esc_html_e( 'Mode de synchronisation Google Agenda', 'mp-agenda' ); ?></label>
				<div class="mp-agenda-sync-mode-options">
					<label class="mp-agenda-sync-mode-option">
						<input type="radio" name="google_sync_mode" value="individual" <?php checked( $mp_google_sync_mode, 'individual' ); ?> />
						<span class="mp-agenda-sync-mode-content">
							<strong><?php esc_html_e( 'Individuel (recommandé)', 'mp-agenda' ); ?></strong>
							<span><?php esc_html_e( 'Chaque commercial se connecte avec son propre compte Google. Ses RDV n\'apparaissent que dans son agenda personnel.', 'mp-agenda' ); ?></span>
						</span>
					</label>
					<label class="mp-agenda-sync-mode-option">
						<input type="radio" name="google_sync_mode" value="shared" <?php checked( $mp_google_sync_mode, 'shared' ); ?> />
						<span class="mp-agenda-sync-mode-content">
							<strong><?php esc_html_e( 'Partagé', 'mp-agenda' ); ?></strong>
							<span><?php esc_html_e( 'Tous les commerciaux utilisent le même compte Google. Tous les RDV apparaissent dans un seul agenda centralisé.', 'mp-agenda' ); ?></span>
						</span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Basculer entre les deux modes est instantané et ne supprime aucune donnée ni aucun jeton déjà connecté : seul le jeu de jetons utilisé change.', 'mp-agenda' ); ?></p>
			</div>

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
			<input type="hidden" name="mp_agenda_notifications_tab" value="1" />
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

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="notify_reminder" value="1" <?php checked( ! array_key_exists( 'notify_reminder', $mp_settings ) || ! empty( $mp_settings['notify_reminder'] ) ); ?> />
					<span><?php esc_html_e( 'Envoyer un email de rappel 24 heures avant le rendez-vous', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<p class="description"><?php esc_html_e( 'Les modèles d\'emails se trouvent dans /templates/emails/ et peuvent être personnalisés par un développeur.', 'mp-agenda' ); ?></p>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mp_agenda_send_test_reminder' ), 'mp_agenda_send_test_reminder' ) ); ?>"><?php esc_html_e( 'Envoyer un rappel test', 'mp-agenda' ); ?></a>
			</div>
			<p class="description"><?php esc_html_e( 'Le rappel test est envoyé à l\'adresse d\'envoi des notifications (ou à l\'email administrateur du site).', 'mp-agenda' ); ?></p>
		</form>

	<?php elseif ( 'sms' === $mp_active_tab ) : ?>
		<?php
		$mp_sms_obj    = new MP_Agenda_SMS();
		$mp_sms_issues = $mp_sms_obj->get_config_issues();
		?>
		<?php if ( empty( $mp_sms_issues ) ) : ?>
			<div class="notice notice-success inline" style="margin:0 0 16px;">
				<p><?php esc_html_e( 'Configuration SMS complète : les rappels et le SMS de test peuvent être envoyés.', 'mp-agenda' ); ?></p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline" style="margin:0 0 16px;">
				<p>
					<strong><?php esc_html_e( 'Les SMS ne partiront pas tant que :', 'mp-agenda' ); ?></strong>
				</p>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $mp_sms_issues as $mp_issue ) : ?>
						<li><?php echo esc_html( $mp_issue ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><?php esc_html_e( 'Renseignez les champs ci-dessous puis cliquez sur « Enregistrer » avant de tester.', 'mp-agenda' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_save_sms_settings' ); ?>
			<input type="hidden" name="action" value="mp_agenda_save_sms_settings" />

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="sms_enabled" value="1" <?php checked( ! empty( $mp_sms['enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Activer les rappels SMS (via OVH)', 'mp-agenda' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Les SMS ne sont envoyés que si cette case est cochée ET que le service + les 3 clés API OVH sont renseignés.', 'mp-agenda' ); ?></p>
			</div>

			<hr class="mp-agenda-separator" />

			<div class="mp-agenda-field">
				<label for="sms_service_name"><?php esc_html_e( 'Service OVH SMS', 'mp-agenda' ); ?></label>
				<input type="text" id="sms_service_name" name="sms_service_name" value="<?php echo esc_attr( $mp_sms['service_name'] ); ?>" />
			</div>

			<div class="mp-agenda-field">
				<label for="sms_app_key"><?php esc_html_e( 'Application Key', 'mp-agenda' ); ?></label>
				<input type="text" id="sms_app_key" name="sms_app_key" value="<?php echo esc_attr( $mp_sms['app_key'] ); ?>" autocomplete="off" />
			</div>

			<div class="mp-agenda-field">
				<label for="sms_app_secret"><?php esc_html_e( 'Application Secret', 'mp-agenda' ); ?></label>
				<input type="password" id="sms_app_secret" name="sms_app_secret" value="<?php echo esc_attr( $mp_sms['app_secret'] ); ?>" autocomplete="off" />
			</div>

			<div class="mp-agenda-field">
				<label for="sms_consumer_key"><?php esc_html_e( 'Consumer Key', 'mp-agenda' ); ?></label>
				<input type="text" id="sms_consumer_key" name="sms_consumer_key" value="<?php echo esc_attr( $mp_sms['consumer_key'] ); ?>" autocomplete="off" />
			</div>

			<div class="mp-agenda-field">
				<label for="sms_sender"><?php esc_html_e( 'Nom de l\'expéditeur (11 caractères max)', 'mp-agenda' ); ?></label>
				<input type="text" id="sms_sender" name="sms_sender" maxlength="11" value="<?php echo esc_attr( $mp_sms['sender'] ); ?>" />
				<p class="description">
					<?php esc_html_e( 'Laissez vide pour utiliser automatiquement le premier expéditeur validé sur votre compte OVH.', 'mp-agenda' ); ?>
				</p>
				<p class="description" style="margin-top:6px;">
					<?php esc_html_e( 'Expéditeurs disponibles OVH :', 'mp-agenda' ); ?>
					<span id="mp-agenda-sms-senders"><?php esc_html_e( 'Chargement…', 'mp-agenda' ); ?></span>
					<button type="button" class="button button-small" id="mp-agenda-sms-senders-refresh"><?php esc_html_e( 'Rafraîchir', 'mp-agenda' ); ?></button>
				</p>
			</div>

			<div class="mp-agenda-field">
				<label><?php esc_html_e( 'Crédits SMS restants', 'mp-agenda' ); ?></label>
				<p><strong id="mp-agenda-sms-credits"><?php esc_html_e( 'Chargement…', 'mp-agenda' ); ?></strong></p>
			</div>

			<hr class="mp-agenda-separator" />

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="sms_reminder_j3" value="1" <?php checked( ! empty( $mp_sms['reminder_j3'] ) ); ?> />
					<span><?php esc_html_e( 'Envoyer un SMS de rappel 3 jours avant', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<div class="mp-agenda-field">
				<label for="sms_message_j3"><?php esc_html_e( 'Message J-3', 'mp-agenda' ); ?></label>
				<textarea id="sms_message_j3" name="sms_message_j3" rows="3"><?php echo esc_textarea( $mp_sms['message_j3'] ); ?></textarea>
			</div>

			<div class="mp-agenda-field">
				<label class="mp-agenda-toggle">
					<input type="checkbox" name="sms_reminder_j1" value="1" <?php checked( ! empty( $mp_sms['reminder_j1'] ) ); ?> />
					<span><?php esc_html_e( 'Envoyer un SMS de rappel 24 heures avant', 'mp-agenda' ); ?></span>
				</label>
			</div>

			<div class="mp-agenda-field">
				<label for="sms_message_j1"><?php esc_html_e( 'Message J-1', 'mp-agenda' ); ?></label>
				<textarea id="sms_message_j1" name="sms_message_j1" rows="3"><?php echo esc_textarea( $mp_sms['message_j1'] ); ?></textarea>
			</div>

			<p class="description">
				<?php esc_html_e( 'Variables disponibles :', 'mp-agenda' ); ?>
				<code>{client_name}</code> <code>{date}</code> <code>{heure}</code> <code>{service}</code> <code>{commercial}</code> <code>{company_name}</code> <code>{manage_url}</code>
			</p>

			<div class="mp-agenda-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'mp-agenda' ); ?></button>
			</div>
		</form>

		<hr class="mp-agenda-separator" />

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mp-agenda-form">
			<?php wp_nonce_field( 'mp_agenda_send_test_sms' ); ?>
			<input type="hidden" name="action" value="mp_agenda_send_test_sms" />
			<div class="mp-agenda-field">
				<label for="sms_test_phone"><?php esc_html_e( 'Numéro pour le SMS de test', 'mp-agenda' ); ?></label>
				<input type="text" id="sms_test_phone" name="sms_test_phone" placeholder="06 12 34 56 78" />
			</div>
			<div class="mp-agenda-form-actions">
				<button type="submit" class="button"><?php esc_html_e( 'Envoyer un SMS test', 'mp-agenda' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Enregistrez d\'abord vos clés API, puis testez. Chaque envoi est journalisé dans error_log.', 'mp-agenda' ); ?></p>
		</form>

		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				if ( typeof window.mpAgendaAdmin === 'undefined' ) {
					return;
				}

				function mpAgendaSmsFetch( action ) {
					var body = new FormData();
					body.append( 'action', action );
					body.append( 'nonce', window.mpAgendaAdmin.nonce );
					return fetch( window.mpAgendaAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } );
				}

				var creditsEl = document.getElementById( 'mp-agenda-sms-credits' );
				if ( creditsEl ) {
					mpAgendaSmsFetch( 'mp_agenda_sms_credits' )
						.then( function ( json ) {
							if ( json && json.success && json.data && typeof json.data.credits !== 'undefined' ) {
								creditsEl.textContent = json.data.credits;
							} else {
								creditsEl.textContent = ( json && json.data && json.data.message ) ? json.data.message : '—';
							}
						} )
						.catch( function () { creditsEl.textContent = '—'; } );
				}

				var sendersEl = document.getElementById( 'mp-agenda-sms-senders' );
				var sendersBtn = document.getElementById( 'mp-agenda-sms-senders-refresh' );
				function loadSenders() {
					if ( ! sendersEl ) {
						return;
					}
					sendersEl.textContent = '<?php echo esc_js( __( 'Chargement…', 'mp-agenda' ) ); ?>';
					mpAgendaSmsFetch( 'mp_agenda_sms_senders' )
						.then( function ( json ) {
							if ( json && json.success && json.data && Array.isArray( json.data.senders ) ) {
								sendersEl.textContent = json.data.senders.length
									? json.data.senders.join( ', ' )
									: '<?php echo esc_js( __( 'aucun expéditeur déclaré sur le compte OVH', 'mp-agenda' ) ); ?>';
							} else {
								sendersEl.textContent = ( json && json.data && json.data.message ) ? json.data.message : '—';
							}
						} )
						.catch( function () { sendersEl.textContent = '—'; } );
				}
				if ( sendersBtn ) {
					sendersBtn.addEventListener( 'click', loadSenders );
				}
				loadSenders();
			} );
		</script>

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
