<?php
/**
 * Template email : suivi envoyé au client lorsqu'il modifie ou annule son
 * rendez-vous depuis la page publique "Gérer mon rendez-vous".
 *
 * Variables disponibles : $appointment, $technician, $settings, $date,
 * $change_type ('modified'|'cancelled'), $manage_url, $calendar_links.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_name    = $settings['company_name'] ?? get_bloginfo( 'name' );
$change_type     = ( isset( $change_type ) && 'cancelled' === $change_type ) ? 'cancelled' : 'modified';
$manage_url      = $manage_url ?? '';
$calendar_links  = isset( $calendar_links ) && is_array( $calendar_links ) ? $calendar_links : array();
$mp_cal_btn_style = 'display:inline-block;padding:10px 20px;border-radius:6px;background:#ffffff;border:1px solid #e5e7eb;color:#1E293B;text-decoration:none;font-size:13px;font-weight:600;margin:4px 6px 4px 0;';
$is_cancelled    = ( 'cancelled' === $change_type );
$header_color    = $is_cancelled ? '#dc2626' : '#61CE70';
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
						<td style="background:<?php echo esc_attr( $header_color ); ?>;padding:20px 32px;">
							<h1 style="color:#ffffff;font-size:18px;margin:0;"><?php echo esc_html( $company_name ); ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<?php if ( $is_cancelled ) : ?>
								<h2 style="font-size:20px;margin:0 0 16px;">Votre rendez-vous a été annulé</h2>
								<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $appointment['client_name'] ); ?>,</p>
								<p style="font-size:14px;line-height:1.6;">Votre rendez-vous du <?php echo esc_html( $date->format( 'd/m/Y' ) ); ?> à <?php echo esc_html( $date->format( 'H:i' ) ); ?> a bien été annulé. Nous restons à votre disposition pour en reprogrammer un autre.</p>
							<?php else : ?>
								<h2 style="font-size:20px;margin:0 0 16px;">Votre rendez-vous a été modifié</h2>
								<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $appointment['client_name'] ); ?>,</p>
								<p style="font-size:14px;line-height:1.6;">Votre rendez-vous a bien été reprogrammé avec les détails suivants :</p>

								<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f8fafc;border-radius:8px;font-size:14px;">
									<tr><td><strong>Commercial</strong></td><td><?php echo esc_html( $technician['name'] ?? '' ); ?></td></tr>
									<tr><td><strong>Date</strong></td><td><?php echo esc_html( $date->format( 'd/m/Y' ) ); ?></td></tr>
									<tr><td><strong>Heure</strong></td><td><?php echo esc_html( $date->format( 'H:i' ) ); ?></td></tr>
									<tr><td><strong>Adresse</strong></td><td><?php echo esc_html( $appointment['client_address'] ); ?></td></tr>
									<tr><td><strong>Service</strong></td><td><?php echo esc_html( ! empty( $appointment['service_name'] ) ? $appointment['service_name'] : $appointment['intervention_type'] ); ?></td></tr>
								</table>

								<?php if ( ! empty( $calendar_links ) ) : ?>
								<p style="font-size:14px;line-height:1.6;margin:24px 0 8px;"><strong>Mettre à jour votre calendrier</strong></p>
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

								<?php if ( ! empty( $manage_url ) ) : ?>
								<p style="font-size:13px;line-height:1.6;margin-top:24px;">
									Besoin de changer à nouveau ?
									<a href="<?php echo esc_url( $manage_url ); ?>" style="color:#1E293B;">Gérer mon rendez-vous</a>
								</p>
								<?php endif; ?>
							<?php endif; ?>
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
