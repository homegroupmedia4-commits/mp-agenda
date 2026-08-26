<?php
/**
 * Template email : notification de nouveau rendez-vous envoyée au technicien.
 *
 * Variables disponibles : $appointment, $technician, $settings, $date.
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
						<td style="background:#16a34a;padding:20px 32px;">
							<h1 style="color:#ffffff;font-size:18px;margin:0;">Nouveau rendez-vous</h1>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<p style="font-size:14px;line-height:1.6;">Bonjour <?php echo esc_html( $technician['name'] ); ?>,</p>
							<p style="font-size:14px;line-height:1.6;">Un nouveau rendez-vous vient d'être planifié :</p>

							<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="background:#f8fafc;border-radius:8px;font-size:14px;">
								<tr><td><strong>Client</strong></td><td><?php echo esc_html( $appointment['client_name'] ); ?></td></tr>
								<tr><td><strong>Téléphone</strong></td><td><?php echo esc_html( $appointment['client_phone'] ); ?></td></tr>
								<?php if ( ! empty( $appointment['client_email'] ) ) : ?>
								<tr><td><strong>Email</strong></td><td><?php echo esc_html( $appointment['client_email'] ); ?></td></tr>
								<?php endif; ?>
								<tr><td><strong>Date</strong></td><td><?php echo esc_html( $date->format( 'd/m/Y' ) ); ?></td></tr>
								<tr><td><strong>Heure</strong></td><td><?php echo esc_html( $date->format( 'H:i' ) ); ?></td></tr>
								<tr><td><strong>Adresse</strong></td><td><?php echo esc_html( $appointment['client_address'] ); ?></td></tr>
								<tr><td><strong>Service</strong></td><td><?php echo esc_html( ! empty( $appointment['service_name'] ) ? $appointment['service_name'] : $appointment['intervention_type'] ); ?></td></tr>
								<?php if ( ! empty( $appointment['surface'] ) ) : ?>
								<tr><td><strong>Surface estimée</strong></td><td><?php echo esc_html( $appointment['surface'] ); ?></td></tr>
								<?php endif; ?>
								<tr><td><strong>Urgence</strong></td><td><?php echo esc_html( 'urgent' === $appointment['urgency'] ? 'Urgent' : 'Normal' ); ?></td></tr>
								<?php if ( ! empty( $appointment['notes'] ) ) : ?>
								<tr><td><strong>Description client</strong></td><td><?php echo esc_html( $appointment['notes'] ); ?></td></tr>
								<?php endif; ?>
								<?php if ( ! empty( $appointment['internal_notes'] ) ) : ?>
								<tr><td><strong>Notes internes</strong></td><td><?php echo esc_html( $appointment['internal_notes'] ); ?></td></tr>
								<?php endif; ?>
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
