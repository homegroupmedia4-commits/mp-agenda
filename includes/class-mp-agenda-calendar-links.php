<?php
/**
 * Génère les liens "Ajouter au calendrier" (Google, Outlook, Apple) et le
 * fichier .ics pour un rendez-vous, ainsi que les tokens signés qui sécurisent
 * l'export .ics et la page publique de gestion du rendez-vous.
 *
 * @package MP_Agenda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MP_Agenda_Calendar_Links.
 */
class MP_Agenda_Calendar_Links {

	/**
	 * Chaîne de contexte incluse dans le hash HMAC (évite qu'un token soit
	 * réutilisable pour un autre usage).
	 *
	 * @var string
	 */
	const TOKEN_CONTEXT = 'mp_agenda_calendar_link';

	/**
	 * Calcule le token signé d'un rendez-vous (hash_hmac basé sur wp_salt).
	 *
	 * @param int $appointment_id ID du rendez-vous.
	 * @return string
	 */
	public static function generate_token( $appointment_id ) {
		return hash_hmac(
			'sha256',
			self::TOKEN_CONTEXT . '|' . absint( $appointment_id ),
			wp_salt( 'auth' )
		);
	}

	/**
	 * Vérifie qu'un token correspond bien au rendez-vous demandé.
	 *
	 * @param int    $appointment_id ID du rendez-vous.
	 * @param string $token          Token fourni dans l'URL.
	 * @return bool
	 */
	public static function verify_token( $appointment_id, $token ) {
		$appointment_id = absint( $appointment_id );
		$token          = (string) $token;

		if ( ! $appointment_id || '' === $token ) {
			return false;
		}

		return hash_equals( self::generate_token( $appointment_id ), $token );
	}

	/**
	 * URL de téléchargement du fichier .ics (transport admin-ajax.php).
	 *
	 * @param int $appointment_id ID du rendez-vous.
	 * @return string
	 */
	public static function export_url( $appointment_id ) {
		return add_query_arg(
			array(
				'action'         => 'mp_agenda_calendar_export',
				'appointment_id' => absint( $appointment_id ),
				'token'          => self::generate_token( $appointment_id ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * URL de la page publique "Gérer mon rendez-vous" pour un rendez-vous donné.
	 *
	 * @param int $appointment_id ID du rendez-vous.
	 * @return string
	 */
	public static function manage_url( $appointment_id ) {
		$page_id = (int) get_option( 'mp_agenda_manage_page_id' );
		$base    = ( $page_id && 'publish' === get_post_status( $page_id ) ) ? get_permalink( $page_id ) : home_url( '/' );

		return add_query_arg(
			array(
				'appointment_id' => absint( $appointment_id ),
				'token'          => self::generate_token( $appointment_id ),
			),
			$base
		);
	}

	/**
	 * URL "Ajouter à Google Agenda" (assistant de création d'événement Google).
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return string
	 */
	public static function google_url( $appointment ) {
		$fields = self::event_fields( $appointment );

		$args = array(
			'action'   => 'TEMPLATE',
			'text'     => $fields['summary'],
			'dates'    => $fields['start'] . '/' . $fields['end'],
			'details'  => $fields['description'],
			'location' => $fields['location'],
		);

		return 'https://calendar.google.com/calendar/render?' . http_build_query( $args );
	}

	/**
	 * Retourne les trois liens calendrier prêts à l'emploi pour un rendez-vous.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return array{google:string,ics:string,manage:string}
	 */
	public static function all_links( $appointment ) {
		$id = (int) ( $appointment['id'] ?? 0 );

		return array(
			'google' => self::google_url( $appointment ),
			'ics'    => self::export_url( $id ),
			'manage' => self::manage_url( $id ),
		);
	}

	/**
	 * Construit le contenu d'un fichier .ics standard (RFC 5545) pour un rendez-vous.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return string
	 */
	public static function generate_ics( $appointment ) {
		$fields = self::event_fields( $appointment );
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = $domain ? $domain : 'mp-agenda';

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//MP Agenda//FR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . absint( $appointment['id'] ?? 0 ) . '@' . $domain,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $fields['start'],
			'DTEND:' . $fields['end'],
			'SUMMARY:' . self::escape_text( $fields['summary'] ),
			'DESCRIPTION:' . self::escape_text( $fields['description'] ),
			'LOCATION:' . self::escape_text( $fields['location'] ),
			'STATUS:CONFIRMED',
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Prépare les champs communs d'un événement (titre, description, lieu, dates)
	 * partagés entre le lien Google et le fichier .ics.
	 *
	 * @param array $appointment Données du rendez-vous.
	 * @return array{summary:string,description:string,location:string,start:string,end:string}
	 */
	private static function event_fields( $appointment ) {
		$settings     = get_option( 'mp_agenda_settings', array() );
		$company_name = $settings['company_name'] ?? get_bloginfo( 'name' );

		$technician_name = $appointment['technician_name'] ?? '';
		if ( '' === $technician_name && ! empty( $appointment['technician_id'] ) ) {
			$technician      = MP_Agenda_DB::get_technician( $appointment['technician_id'] );
			$technician_name = $technician['name'] ?? '';
		}

		$service_name = ! empty( $appointment['service_name'] )
			? $appointment['service_name']
			: ( $appointment['intervention_type'] ?? '' );

		try {
			$start_dt = new DateTime( $appointment['start_datetime'] );
		} catch ( \Exception $e ) {
			$start_dt = new DateTime();
		}

		if ( ! empty( $appointment['end_datetime'] ) ) {
			try {
				$end_dt = new DateTime( $appointment['end_datetime'] );
			} catch ( \Exception $e ) {
				$end_dt = clone $start_dt;
				$end_dt->modify( '+' . ( absint( $appointment['duration'] ?? 60 ) ?: 60 ) . ' minutes' );
			}
		} else {
			$end_dt = clone $start_dt;
			$end_dt->modify( '+' . ( absint( $appointment['duration'] ?? 60 ) ?: 60 ) . ' minutes' );
		}

		if ( '' !== trim( (string) $service_name ) ) {
			$summary = sprintf(
				/* translators: 1: nom du service, 2: nom de l'entreprise */
				__( 'RDV %1$s — %2$s', 'mp-agenda' ),
				$service_name,
				$company_name
			);
		} else {
			/* translators: %s: nom de l'entreprise */
			$summary = sprintf( __( 'RDV %s', 'mp-agenda' ), $company_name );
		}

		$description = sprintf(
			/* translators: 1: nom du commercial, 2: téléphone du client */
			__( "Commercial : %1\$s\nTéléphone : %2\$s", 'mp-agenda' ),
			$technician_name,
			$appointment['client_phone'] ?? ''
		);

		return array(
			'summary'     => $summary,
			'description' => $description,
			'location'    => (string) ( $appointment['client_address'] ?? '' ),
			'start'       => $start_dt->format( 'Ymd\THis' ),
			'end'         => $end_dt->format( 'Ymd\THis' ),
		);
	}

	/**
	 * Échappe une valeur texte pour un champ .ics (RFC 5545 : \\ ; , et retours ligne).
	 *
	 * @param string $text Texte brut.
	 * @return string
	 */
	private static function escape_text( $text ) {
		$text = (string) $text;
		$text = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $text );
		$text = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $text );

		return $text;
	}
}
