=== MP Agenda ===
Contributors: mprenov
Tags: rendez-vous, planning, calendrier, google agenda, réservation
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Système de prise de rendez-vous simplifié pour une petite entreprise de rénovation, avec planning visuel, formulaire client et synchronisation Google Agenda.

== Description ==

MP Agenda est un plugin de prise de rendez-vous épuré, pensé pour une petite équipe de terrain (par défaut deux techniciens, Alexandre et Kamal). Il propose :

* Un planning visuel en vue jour/semaine dans l'administration WordPress.
* Une gestion complète des rendez-vous (création, modification, annulation, statuts).
* Une fiche par technicien avec horaires de travail personnalisés.
* Un formulaire de réservation client en 4 étapes via le shortcode `[mp_agenda_booking]`.
* Une synchronisation bidirectionnelle avec Google Agenda (OAuth 2.0).
* Des notifications email automatiques (client et technicien).
* Un export CSV des rendez-vous.
* Une mention RGPD personnalisable sous le formulaire client.

== Installation ==

1. Téléversez le dossier `mp-agenda` (ou l'archive .zip) dans `/wp-content/plugins/`.
2. Activez le plugin depuis le menu "Extensions" de WordPress.
3. Rendez-vous dans "MP Agenda > Techniciens" pour configurer Alexandre et Kamal.
4. Configurez éventuellement l'API Google dans "MP Agenda > Réglages > Google API".
5. Ajoutez le shortcode `[mp_agenda_booking]` sur la page de votre choix pour afficher le formulaire de réservation.

== Frequently Asked Questions ==

= Le plugin nécessite-t-il Google Agenda ? =

Non, la synchronisation Google Agenda est optionnelle. Le plugin fonctionne pleinement sans elle.

= Puis-je ajouter plus de deux techniciens ? =

Oui, la page "Techniciens" permet d'ajouter, modifier ou supprimer autant de techniciens que nécessaire.

== Changelog ==

= 1.0.0 =
* Version initiale.
