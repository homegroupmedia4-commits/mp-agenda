<?php
/**
 * Template email : notification envoyée au commercial lorsqu'un client modifie
 * ou annule son rendez-vous depuis la page publique "Gérer mon rendez-vous".
 *
 * Variables disponibles : $appointment, $technician, $settings, $date,
 * $change_type ('modified'|'cancelled').
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_name = $settings['company_name'] ?? get_bloginfo( 'name' );
$change_type  = ( isset( $change_type ) && 'cancelled' === $change_type ) ? 'cancelled' : 'modified';
$is_cancelled = ( 'cancelled' === $change_type );
$header_color = $is_cancelled ? '#dc2626' : '#16a34a';
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
							<h1 style="color:#ffffff;font-size:18px;margin:0;"><?php echo $is_cancelled ? 'Rendez-vous annulé' : 'Rendez-vous modifié'; ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $technician['name'] ); ?>,</p>
							<?php if ( $is_cancelled ) : ?>
								<p style="font-size:14px;line-height:1.6;">Le client a annulé le rendez-vous suivant :</p>
							<?php else : ?>
								<p style="font-size:14px;line-height:1.6;">Le client a reprogrammé son rendez-vous. Nouveaux détails :</p>
							<?php endif; ?>

							<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f8fafc;border-radius:8px;font-size:14px;">
								<tr><td><strong>Client</strong></td><td><?php echo esc_html( $appointment['client_name'] ); ?></td></tr>
								<tr><td><strong>Téléphone</strong></td><td><?php echo esc_html( $appointment['client_phone'] ); ?></td></tr>
								<tr><td><strong>Date</strong></td><td><?php echo esc_html( $date->format( 'd/m/Y' ) ); ?></td></tr>
								<tr><td><strong>Heure</strong></td><td><?php echo esc_html( $date->format( 'H:i' ) ); ?></td></tr>
								<tr><td><strong>Adresse</strong></td><td><?php echo esc_html( $appointment['client_address'] ); ?></td></tr>
								<tr><td><strong>Service</strong></td><td><?php echo esc_html( ! empty( $appointment['service_name'] ) ? $appointment['service_name'] : $appointment['intervention_type'] ); ?></td></tr>
							</table>
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
