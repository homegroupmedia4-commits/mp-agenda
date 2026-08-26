<?php
/**
 * Vue front-end : formulaire de prise de rendez-vous en 6 étapes.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_default_technician = isset( $atts['default_technician'] ) ? absint( $atts['default_technician'] ) : 0;
?>
<div class="mp-agenda-booking" id="mp-agenda-booking-root" data-default-technician="<?php echo esc_attr( $mp_default_technician ); ?>">

	<div class="mp-agenda-steps">
		<div class="mp-agenda-step-indicator active" data-step="1">
			<span class="mp-agenda-step-number">1</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Showroom', 'mp-agenda' ); ?></span>
		</div>
		<div class="mp-agenda-step-indicator" data-step="2">
			<span class="mp-agenda-step-number">2</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Services', 'mp-agenda' ); ?></span>
		</div>
		<div class="mp-agenda-step-indicator" data-step="3">
			<span class="mp-agenda-step-number">3</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Commercial', 'mp-agenda' ); ?></span>
		</div>
		<div class="mp-agenda-step-indicator" data-step="4">
			<span class="mp-agenda-step-number">4</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Date & heure', 'mp-agenda' ); ?></span>
		</div>
		<div class="mp-agenda-step-indicator" data-step="5">
			<span class="mp-agenda-step-number">5</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Vos informations', 'mp-agenda' ); ?></span>
		</div>
		<div class="mp-agenda-step-indicator" data-step="6">
			<span class="mp-agenda-step-number">6</span>
			<span class="mp-agenda-step-label"><?php esc_html_e( 'Confirmation', 'mp-agenda' ); ?></span>
		</div>
	</div>

	<div class="mp-agenda-shell">

		<aside class="mp-agenda-sidebar-left">
			<div class="mp-agenda-sidebar-icon" id="mp-agenda-sidebar-icon">🏬</div>
			<h4 class="mp-agenda-sidebar-title" id="mp-agenda-sidebar-title"><?php esc_html_e( 'Showroom', 'mp-agenda' ); ?></h4>
			<p class="mp-agenda-sidebar-desc" id="mp-agenda-sidebar-desc"><?php esc_html_e( 'Choisissez le showroom le plus proche de chez vous.', 'mp-agenda' ); ?></p>

			<div class="mp-agenda-sidebar-phone" id="mp-agenda-sidebar-phone" hidden>
				<span class="mp-agenda-sidebar-phone-label"><?php esc_html_e( 'Une question ?', 'mp-agenda' ); ?></span>
				<a href="#" class="mp-agenda-sidebar-phone-number" id="mp-agenda-sidebar-phone-link"></a>
			</div>
		</aside>

		<main class="mp-agenda-main">
			<form id="mp-agenda-booking-form" novalidate>

				<!-- Étape 1 : Showroom -->
				<section class="mp-agenda-panel is-active" data-step-panel="1">
					<h3><?php esc_html_e( 'Choisissez votre showroom', 'mp-agenda' ); ?></h3>
					<div class="mp-agenda-showroom-cards" id="mp-agenda-showroom-cards">
						<div class="mp-agenda-loading"><?php esc_html_e( 'Chargement…', 'mp-agenda' ); ?></div>
					</div>
					<div class="mp-agenda-panel-actions">
						<span></span>
						<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" data-next="2"><?php esc_html_e( 'Continuer', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Étape 2 : Services -->
				<section class="mp-agenda-panel" data-step-panel="2">
					<h3><?php esc_html_e( 'Quel service vous intéresse ?', 'mp-agenda' ); ?></h3>
					<div class="mp-agenda-service-cards" id="mp-agenda-service-cards">
						<div class="mp-agenda-loading"><?php esc_html_e( 'Chargement…', 'mp-agenda' ); ?></div>
					</div>
					<div class="mp-agenda-panel-actions">
						<button type="button" class="mp-agenda-btn mp-agenda-btn-secondary" data-prev="1"><?php esc_html_e( 'Retour', 'mp-agenda' ); ?></button>
						<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" data-next="3"><?php esc_html_e( 'Continuer', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Étape 3 : Commercial -->
				<section class="mp-agenda-panel" data-step-panel="3">
					<h3><?php esc_html_e( 'Choisissez votre commercial', 'mp-agenda' ); ?></h3>
					<div class="mp-agenda-technician-cards" id="mp-agenda-technician-cards">
						<div class="mp-agenda-loading"><?php esc_html_e( 'Chargement…', 'mp-agenda' ); ?></div>
					</div>
					<div class="mp-agenda-panel-actions">
						<button type="button" class="mp-agenda-btn mp-agenda-btn-secondary" data-prev="2"><?php esc_html_e( 'Retour', 'mp-agenda' ); ?></button>
						<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" data-next="4"><?php esc_html_e( 'Continuer', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Étape 4 : Date & créneau -->
				<section class="mp-agenda-panel" data-step-panel="4">
					<h3><?php esc_html_e( 'Choisissez une date et un créneau', 'mp-agenda' ); ?></h3>

					<div class="mp-agenda-mini-calendar">
						<div class="mp-agenda-mini-cal-header">
							<button type="button" class="mp-agenda-cal-nav" id="mp-agenda-cal-prev" aria-label="<?php esc_attr_e( 'Mois précédent', 'mp-agenda' ); ?>">&larr;</button>
							<span id="mp-agenda-cal-label"></span>
							<button type="button" class="mp-agenda-cal-nav" id="mp-agenda-cal-next" aria-label="<?php esc_attr_e( 'Mois suivant', 'mp-agenda' ); ?>">&rarr;</button>
						</div>
						<div class="mp-agenda-mini-cal-weekdays" id="mp-agenda-cal-weekdays"></div>
						<div class="mp-agenda-mini-cal-days" id="mp-agenda-cal-days"></div>
					</div>

					<div class="mp-agenda-slots-wrap">
						<h4 id="mp-agenda-slots-title"></h4>
						<div class="mp-agenda-slots" id="mp-agenda-slots"></div>
					</div>

					<input type="hidden" id="mp-agenda-selected-technician" name="technician_id" />
					<input type="hidden" id="mp-agenda-selected-date" name="date" />
					<input type="hidden" id="mp-agenda-selected-time" name="time" />

					<div class="mp-agenda-panel-actions">
						<button type="button" class="mp-agenda-btn mp-agenda-btn-secondary" data-prev="3"><?php esc_html_e( 'Retour', 'mp-agenda' ); ?></button>
						<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" data-next="5"><?php esc_html_e( 'Continuer', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Étape 5 : Informations client -->
				<section class="mp-agenda-panel" data-step-panel="5">
					<h3><?php esc_html_e( 'Vos informations', 'mp-agenda' ); ?></h3>

					<div class="mp-agenda-field">
						<label for="mp-agenda-name"><?php esc_html_e( 'Nom complet', 'mp-agenda' ); ?> *</label>
						<input type="text" id="mp-agenda-name" name="client_name" required />
					</div>

					<div class="mp-agenda-field-row">
						<div class="mp-agenda-field">
							<label for="mp-agenda-phone"><?php esc_html_e( 'Téléphone', 'mp-agenda' ); ?> *</label>
							<input type="tel" id="mp-agenda-phone" name="client_phone" required />
						</div>
						<div class="mp-agenda-field">
							<label for="mp-agenda-email"><?php esc_html_e( 'Email', 'mp-agenda' ); ?></label>
							<input type="email" id="mp-agenda-email" name="client_email" />
						</div>
					</div>

					<div class="mp-agenda-field">
						<label for="mp-agenda-address"><?php esc_html_e( 'Adresse du chantier', 'mp-agenda' ); ?> *</label>
						<textarea id="mp-agenda-address" name="client_address" rows="2" required></textarea>
					</div>

					<div class="mp-agenda-field">
						<label for="mp-agenda-notes"><?php esc_html_e( 'Description de votre besoin', 'mp-agenda' ); ?></label>
						<textarea id="mp-agenda-notes" name="notes" rows="3"></textarea>
					</div>

					<div class="mp-agenda-field mp-agenda-gdpr-field">
						<label class="mp-agenda-checkbox">
							<input type="checkbox" id="mp-agenda-gdpr" name="gdpr_accepted" required />
							<span><?php esc_html_e( 'J\'accepte la politique de confidentialité', 'mp-agenda' ); ?> *</span>
						</label>
						<p class="mp-agenda-gdpr-text"><?php echo wp_kses_post( get_option( 'mp_agenda_gdpr_text', '' ) ); ?></p>
					</div>

					<div class="mp-agenda-form-error" id="mp-agenda-step5-error" hidden></div>

					<div class="mp-agenda-panel-actions">
						<button type="button" class="mp-agenda-btn mp-agenda-btn-secondary" data-prev="4"><?php esc_html_e( 'Retour', 'mp-agenda' ); ?></button>
						<button type="button" class="mp-agenda-btn mp-agenda-btn-primary" id="mp-agenda-to-recap"><?php esc_html_e( 'Continuer', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Étape 6 : Récapitulatif & confirmation -->
				<section class="mp-agenda-panel" data-step-panel="6">
					<h3><?php esc_html_e( 'Récapitulatif de votre rendez-vous', 'mp-agenda' ); ?></h3>

					<div class="mp-agenda-recap" id="mp-agenda-recap"></div>

					<div class="mp-agenda-form-error" id="mp-agenda-step6-error" hidden></div>

					<div class="mp-agenda-panel-actions">
						<button type="button" class="mp-agenda-btn mp-agenda-btn-secondary" data-prev="5"><?php esc_html_e( 'Retour', 'mp-agenda' ); ?></button>
						<button type="submit" class="mp-agenda-btn mp-agenda-btn-primary" id="mp-agenda-submit"><?php esc_html_e( 'Confirmer mon rendez-vous', 'mp-agenda' ); ?></button>
					</div>
				</section>

				<!-- Message de succès -->
				<section class="mp-agenda-panel mp-agenda-success-panel" data-step-panel="success">
					<div class="mp-agenda-success-icon">&#10003;</div>
					<h3><?php esc_html_e( 'Votre rendez-vous est confirmé !', 'mp-agenda' ); ?></h3>
					<p id="mp-agenda-success-message"></p>
				</section>

			</form>
		</main>

		<aside class="mp-agenda-sidebar-right" id="mp-agenda-sidebar-right">
			<h4 class="mp-agenda-fiche-title"><?php esc_html_e( 'Ma fiche', 'mp-agenda' ); ?></h4>
			<dl class="mp-agenda-fiche">
				<div class="mp-agenda-fiche-row" data-fiche-row="showroom" hidden>
					<dt><?php esc_html_e( 'Showroom', 'mp-agenda' ); ?></dt>
					<dd id="mp-agenda-fiche-showroom"></dd>
				</div>
				<div class="mp-agenda-fiche-row" data-fiche-row="service" hidden>
					<dt><?php esc_html_e( 'Service', 'mp-agenda' ); ?></dt>
					<dd id="mp-agenda-fiche-service"></dd>
				</div>
				<div class="mp-agenda-fiche-row" data-fiche-row="technician" hidden>
					<dt><?php esc_html_e( 'Commercial', 'mp-agenda' ); ?></dt>
					<dd id="mp-agenda-fiche-technician"></dd>
				</div>
				<div class="mp-agenda-fiche-row" data-fiche-row="datetime" hidden>
					<dt><?php esc_html_e( 'Date', 'mp-agenda' ); ?></dt>
					<dd id="mp-agenda-fiche-datetime"></dd>
				</div>
			</dl>
		</aside>

	</div>
</div>
