=== MP Agenda ===
Contributors: mprenov
Tags: rendez-vous, planning, calendrier, google agenda, réservation
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Système de prise de rendez-vous simplifié pour une petite entreprise de rénovation, avec planning visuel, formulaire client et synchronisation Google Agenda.

== Description ==

MP Agenda est un plugin de prise de rendez-vous épuré, pensé pour une petite équipe de terrain (par défaut deux commerciaux, Alexandre et Kamal). Il propose :

* Un planning visuel en vue jour/semaine dans l'administration WordPress.
* Une gestion complète des rendez-vous (création, modification, annulation, statuts).
* Une fiche par commercial avec horaires de travail personnalisés.
* Un formulaire de réservation client en 6 étapes via le shortcode `[mp_agenda_booking]`.
* Une synchronisation bidirectionnelle avec Google Agenda (OAuth 2.0).
* Des notifications email automatiques (client et commercial).
* Un export CSV des rendez-vous.
* Une mention RGPD personnalisable sous le formulaire client.

== Installation ==

1. Téléversez le dossier `mp-agenda` (ou l'archive .zip) dans `/wp-content/plugins/`.
2. Activez le plugin depuis le menu "Extensions" de WordPress.
3. Rendez-vous dans "MP Agenda > Commerciaux" pour configurer Alexandre et Kamal.
4. Configurez éventuellement l'API Google dans "MP Agenda > Réglages > Google API".
5. Ajoutez le shortcode `[mp_agenda_booking]` sur la page de votre choix pour afficher le formulaire de réservation.

== Frequently Asked Questions ==

= Le plugin nécessite-t-il Google Agenda ? =

Non, la synchronisation Google Agenda est optionnelle. Le plugin fonctionne pleinement sans elle.

= Puis-je ajouter plus de deux commerciaux ? =

Oui, la page "Commerciaux" permet d'ajouter, modifier ou supprimer autant de commerciaux que nécessaire.

== Changelog ==

= 1.2.2 =
* Correctif : une réservation en ligne dont l'écriture en base échouait silencieusement (INSERT SQL en échec, ex. colonne manquante) pouvait renvoyer un ID de rendez-vous périmé ou erroné, provoquant l'absence d'email ou l'envoi de notifications pour le mauvais rendez-vous. save_appointment() vérifie désormais le résultat de l'INSERT/UPDATE, et book_appointment() renvoie une erreur explicite au client au lieu de continuer silencieusement.
* Correctif : les échecs d'envoi réels de wp_mail() (SMTP, etc.) sont maintenant tracés dans le journal PHP (hook wp_mail_failed) pour faciliter le diagnostic.
* Correctif : les événements créés directement dans Google Agenda par un commercial étaient importés avec un décalage horaire (conversion UTC au lieu de l'heure locale du site), ce qui pouvait les faire apparaître sur un autre jour/créneau que prévu dans le planning, voire les faire paraître absents. La synchronisation Google → Plugin utilise désormais le fuseau horaire du site, comme le reste du plugin.
* Ajout de traces de diagnostic temporaires dans book_appointment() et sync_technician() (créneaux, réservations, synchro Google) pour faciliter le futur dépannage — à retirer une fois la stabilité confirmée en production.

= 1.2.1 =
* Correctif : les emails de confirmation (client et commercial) n'étaient plus envoyés après une réservation en ligne si la table des services n'avait pas encore été créée en base. get_appointment() se replie désormais sur une requête sans la jointure services, la migration se rejoue à chaque admin_init, et les échecs sont désormais tracés dans le journal PHP.

= 1.2.0 =
* Renommage "Technicien" en "Commercial" dans toute l'interface.
* Ajout des Services (page admin dédiée) en remplacement des types d'intervention.
* Formulaire de réservation en 6 étapes avec nouveau design.

= 1.0.0 =
* Version initiale.
