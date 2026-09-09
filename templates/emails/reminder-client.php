<?php
/**
 * Template email : rappel de rendez-vous envoyé au client ~24 h avant.
 *
 * Variables disponibles : $appointment, $technician, $settings, $date, $cancel_url,
 * $manage_url, $calendar_links (array google|ics|manage).
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_name     = $settings['company_name'] ?? get_bloginfo( 'name' );
$manage_url       = $manage_url ?? '';
$cancel_url       = $cancel_url ?? '';
$calendar_links   = isset( $calendar_links ) && is_array( $calendar_links ) ? $calendar_links : array();
$mp_cal_btn_style = 'display:inline-block;padding:10px 20px;border-radius:6px;background:#ffffff;border:1px solid #e5e7eb;color:#1E293B;text-decoration:none;font-size:13px;font-weight:600;margin:4px 6px 4px 0;';

$mp_technician_name = ! empty( $appointment['technician_name'] ) ? $appointment['technician_name'] : ( $technician['name'] ?? '' );
$mp_service_name    = ! empty( $appointment['service_name'] ) ? $appointment['service_name'] : ( $appointment['intervention_type'] ?? '' );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
		<tr>
			<td align="center">
				<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">
					<tr>
						<td style="background:#61CE70;padding:20px 32px;">
							<h1 style="color:#ffffff;font-size:18px;margin:0;"><?php echo esc_html( $company_name ); ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<h2 style="font-size:20px;margin:0 0 16px;">Rappel de votre rendez-vous</h2>
							<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $appointment['client_name'] ); ?>,</p>
							<p style="font-size:14px;line-height:1.6;">Nous vous rappelons votre rendez-vous prévu demain :</p>

							<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f8fafc;border-radius:8px;font-size:14px;">
								<tr><td><strong>Commercial</strong></td><td><?php echo esc_html( $mp_technician_name ); ?></td></tr>
								<tr><td><strong>Date</strong></td><td><?php echo esc_html( $date->format( 'd/m/Y' ) ); ?></td></tr>
								<tr><td><strong>Heure</strong></td><td><?php echo esc_html( $date->format( 'H:i' ) ); ?></td></tr>
								<tr><td><strong>Adresse</strong></td><td><?php echo esc_html( $appointment['client_address'] ?? '' ); ?></td></tr>
								<tr><td><strong>Service</strong></td><td><?php echo esc_html( $mp_service_name ); ?></td></tr>
							</table>

							<?php if ( ! empty( $calendar_links ) ) : ?>
							<p style="font-size:14px;line-height:1.6;margin:24px 0 8px;"><strong>Ajouter ce rendez-vous à votre calendrier</strong></p>
							<p style="margin:0 0 8px;">
								<?php if ( ! empty( $calendar_links['google'] ) ) : ?>
									<a href="<?php echo esc_url( $calendar_links['google'] ); ?>" style="<?php echo esc_attr( $mp_cal_btn_style ); ?>">Google</a>
								<?php endif; ?>
								<?php if ( ! empty( $calendar_links['ics'] ) ) : ?>
									<a href="<?php echo esc_url( $calendar_links['ics'] ); ?>" style="<?php echo esc_attr( $mp_cal_btn_style ); ?>">Outlook</a>
									<a href="<?php echo esc_url( $calendar_links['ics'] ); ?>" style="<?php echo esc_attr( $mp_cal_btn_style ); ?>">Apple</a>
								<?php endif; ?>
							</p>
							<?php endif; ?>

							<p style="font-size:13px;line-height:1.6;margin-top:24px;">
								<?php if ( ! empty( $manage_url ) ) : ?>
									<a href="<?php echo esc_url( $manage_url ); ?>" style="color:#1E293B;">Modifier mon rendez-vous</a><br />
								<?php endif; ?>
								<?php if ( ! empty( $cancel_url ) ) : ?>
									<a href="<?php echo esc_url( $cancel_url ); ?>" style="color:#dc2626;">Annuler mon rendez-vous</a>
								<?php endif; ?>
							</p>

							<p style="font-size:13px;line-height:1.6;color:#64748b;margin-top:16px;">
								Si vous ne pouvez pas venir, merci de nous prévenir en modifiant ou annulant votre rendez-vous.
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:16px 32px;background:#f8fafc;font-size:12px;color:#64748b;">
							<?php echo esc_html( $company_name ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
