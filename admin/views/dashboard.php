<?php
/**
 * Vue admin : planning (dashboard principal).
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mp_technicians = MP_Agenda_DB::get_technicians( true );
?>
<div class="wrap mp-agenda-wrap mp-agenda-dashboard">
	<h1 class="mp-agenda-title"><?php esc_html_e( 'Planning', 'mp-agenda' ); ?></h1>

	<div class="mp-agenda-toolbar">
		<div class="mp-agenda-toolbar-group">
			<button type="button" class="button mp-agenda-nav-prev" aria-label="<?php esc_attr_e( 'Précédent', 'mp-agenda' ); ?>">&larr;</button>
			<button type="button" class="button mp-agenda-nav-today"><?php esc_html_e( 'Aujourd\'hui', 'mp-agenda' ); ?></button>
			<button type="button" class="button mp-agenda-nav-next" aria-label="<?php esc_attr_e( 'Suivant', 'mp-agenda' ); ?>">&rarr;</button>
			<input type="date" class="mp-agenda-date-picker" />
			<span class="mp-agenda-current-label"></span>
		</div>

		<div class="mp-agenda-toolbar-group">
			<div class="mp-agenda-view-toggle">
				<button type="button" class="button mp-agenda-view-btn is-active" data-view="day"><?php esc_html_e( 'Jour', 'mp-agenda' ); ?></button>
				<button type="button" class="button mp-agenda-view-btn" data-view="week"><?php esc_html_e( 'Semaine', 'mp-agenda' ); ?></button>
			</div>
		</div>

		<div class="mp-agenda-toolbar-group mp-agenda-technician-filters">
			<button type="button" class="button mp-agenda-filter-btn is-active" data-technician="all"><?php esc_html_e( 'Tous', 'mp-agenda' ); ?></button>
			<?php foreach ( $mp_technicians as $mp_tech ) : ?>
				<button type="button" class="button mp-agenda-filter-btn" data-technician="<?php echo esc_attr( $mp_tech['id'] ); ?>"><?php echo esc_html( $mp_tech['name'] ); ?></button>
			<?php endforeach; ?>
			<button type="button" class="button mp-agenda-sync-google-btn">🔄 <?php esc_html_e( 'Synchroniser Google', 'mp-agenda' ); ?></button>
			<span class="mp-agenda-sync-status"></span>
			<button type="button" class="button button-primary mp-agenda-new-appointment"><?php esc_html_e( '+ Nouveau RDV', 'mp-agenda' ); ?></button>
		</div>
	</div>

	<div class="mp-agenda-legend">
		<span class="mp-agenda-legend-item"><span class="mp-agenda-dot mp-agenda-dot-confirmed"></span><?php esc_html_e( 'Confirmé', 'mp-agenda' ); ?></span>
		<span class="mp-agenda-legend-item"><span class="mp-agenda-dot mp-agenda-dot-pending"></span><?php esc_html_e( 'En attente', 'mp-agenda' ); ?></span>
		<span class="mp-agenda-legend-item"><span class="mp-agenda-dot mp-agenda-dot-completed"></span><?php esc_html_e( 'Terminé', 'mp-agenda' ); ?></span>
		<span class="mp-agenda-legend-item"><span class="mp-agenda-dot mp-agenda-dot-cancelled"></span><?php esc_html_e( 'Annulé', 'mp-agenda' ); ?></span>
		<span class="mp-agenda-legend-item"><span class="mp-agenda-dot mp-agenda-dot-blocked"></span><?php esc_html_e( 'Indisponible', 'mp-agenda' ); ?></span>
	</div>

	<div class="mp-agenda-card mp-agenda-calendar-wrap">
		<div id="mp-agenda-calendar" class="mp-agenda-calendar" data-loading="false">
			<div class="mp-agenda-calendar-loading"><?php esc_html_e( 'Chargement du planning…', 'mp-agenda' ); ?></div>
		</div>
	</div>

	<?php
	$mp_technicians_json = wp_json_encode( $mp_technicians );
	?>
	<script>
		window.mpAgendaTechnicians = <?php echo $mp_technicians_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	</script>

	<?php require MP_AGENDA_PLUGIN_DIR . 'admin/views/appointment-modal.php'; ?>
</div>
