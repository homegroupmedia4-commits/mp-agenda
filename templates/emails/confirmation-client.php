<?php
/**
 * Template email : confirmation de rendez-vous envoyée au client.
 *
 * Variables disponibles : $appointment, $technician, $settings, $date, $cancel_url.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_name = $settings['company_name'] ?? get_bloginfo( 'name' );
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
							<h2 style="font-size:20px;margin:0 0 16px;">Votre rendez-vous est confirmé</h2>
							<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $appointment['client_name'] ); ?>,</p>
							<p style="font-size:14px;line-height:1.6;">Votre rendez-vous a bien été enregistré avec les détails suivants :</p>

							<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f8fafc;border-radius:8px;font-size:14px;">
								<tr><td><strong>Technicien</strong></td><td><?php echo esc_html( $technician['name'] ?? '' ); ?></td></tr>
								<tr><td><strong>Date</strong></td><td><?php echo esc_html( $date->format( 'd/m/Y' ) ); ?></td></tr>
								<tr><td><strong>Heure</strong></td><td><?php echo esc_html( $date->format( 'H:i' ) ); ?></td></tr>
								<tr><td><strong>Adresse</strong></td><td><?php echo esc_html( $appointment['client_address'] ); ?></td></tr>
								<tr><td><strong>Type d'intervention</strong></td><td><?php echo esc_html( $appointment['intervention_type'] ); ?></td></tr>
							</table>

							<p style="font-size:13px;line-height:1.6;margin-top:24px;">
								Besoin d'annuler ce rendez-vous ?
								<a href="<?php echo esc_url( $cancel_url ); ?>" style="color:#dc2626;">Annuler mon rendez-vous</a>
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
