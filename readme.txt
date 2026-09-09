=== MP Agenda ===
Contributors: mprenov
Tags: rendez-vous, planning, calendrier, google agenda, réservation
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Système de prise de rendez-vous simplifié pour une petite entreprise de rénovation, avec planning visuel, formulaire client et synchronisation Google Agenda.

== Description ==

MP Agenda est un plugin de prise de rendez-vous épuré, pensé pour une petite équipe de terrain (par défaut deux commerciaux, Alexandre et Kamal). Il propose :

* Un planning visuel en vue jour/semaine/mois dans l'administration WordPress.
* Une gestion complète des rendez-vous (création, modification, annulation, statuts).
* Une fiche par commercial avec horaires de travail personnalisés.
* Un formulaire de réservation client en 6 étapes via le shortcode `[mp_agenda_booking]`.
* Une synchronisation bidirectionnelle avec Google Agenda (OAuth 2.0).
* Des notifications email automatiques (client et commercial).
* Des boutons "Ajouter au calendrier" (Google, Outlook, Apple) pour le client, après réservation et dans l'email de confirmation.
* Une page publique "Gérer mon rendez-vous" (`[mp_agenda_manage]`) permettant au client de reprogrammer ou d'annuler son rendez-vous via un lien sécurisé.
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

= 1.6.0 =
* Ajout : boutons "Ajouter au calendrier" (Google Agenda, Outlook, Apple Calendar) pour le client — à l'étape de confirmation du formulaire de réservation et dans l'email de confirmation. Génération d'un fichier .ics standard servi via une route publique sécurisée par token signé (hash_hmac + wp_salt).
* Ajout : shortcode `[mp_agenda_manage]` et page WordPress "Gérer mon rendez-vous" (créée automatiquement à l'activation, ID stocké dans l'option mp_agenda_manage_page_id). Le client peut y consulter son rendez-vous, le reprogrammer (mêmes contrôles de disponibilité que la réservation, mise à jour de l'événement Google) ou l'annuler. Accès sécurisé par le même token signé.
* Ajout : lien "Modifier mon rendez-vous" dans l'email de confirmation client (à côté du lien d'annulation existant) et mention "Le client peut modifier ou annuler ce rendez-vous" dans l'email commercial.
* Ajout : emails de suivi (client + commercial) lors d'une modification ou d'une annulation faite depuis la page publique.
* Ajout : rappel automatique par email au client ~24 h avant le rendez-vous (nouveau template reminder-client.php avec les boutons calendrier et les liens modifier/annuler). Cron horaire mp_agenda_send_reminders_cron avec fenêtre glissante de 2 h (résiste à un cron WP en retard), flag reminder_sent en base pour ne jamais envoyer deux rappels. Nouveau réglage "Envoyer un email de rappel 24 heures avant le rendez-vous" (coché par défaut) dans Réglages > Notifications, avec bouton "Envoyer un rappel test".
* Le formulaire de réservation, les emails existants, le planning et la synchronisation Google restent inchangés.

= 1.5.0 =
* Ajout : vue "Mois" dans le planning admin — calendrier mensuel classique (grille 7 colonnes) affichant, pour chaque jour, la liste des RDV (heure, nom du client, commercial) colorés par statut ainsi que les créneaux bloqués en gris. Navigation par mois, clic sur un jour pour basculer en vue Jour, clic sur un RDV pour ouvrir la modal d'édition. Le filtre par commercial s'applique aussi en vue Mois.
* Modification : le filtre par commercial du planning devient un menu déroulant ("Tous les commerciaux" + liste des commerciaux) à la place des boutons, avec le même comportement de filtrage.
* Modification : nettoyage de la page Réglages > Google API — l'étape 5 renvoie désormais à la documentation pour l'URI de redirection, et l'encart d'avertissement sur le mode "Testing" a été retiré.
* Les vues Jour et Semaine sont inchangées.

= 1.4.0 =
* Ajout : choix du mode de synchronisation Google Agenda dans Réglages > Google API — Individuel (chaque commercial son propre agenda, comportement historique) ou Partagé (tous les commerciaux utilisent un seul agenda Google centralisé).
* Ajout : en mode Partagé, connexion/déconnexion d'un unique agenda Google depuis MP Agenda > Commerciaux ("Agenda Google partagé"), avec affichage de l'email du compte connecté.
* Ajout : nouvelle méthode centrale get_credentials_for() dans MP_Agenda_Google_Sync — tous les appels Google (renouvellement de token, push/pull de RDV, FreeBusy) passent désormais par elle et utilisent automatiquement les bons identifiants selon le mode configuré.
* Le basculement entre les deux modes est instantané et ne supprime aucun jeton (individuels ou partagé) ni aucune donnée (RDV, créneaux bloqués) : seul le jeu de jetons utilisé change.
* Le mode Individuel reste le comportement par défaut et fonctionne à l'identique des versions précédentes.

= 1.3.0 =
* Ajout : gestion ultra-robuste des tokens Google — renouvellement anticipé (5 min avant expiration), 2 réessais automatiques en cas d'échec réseau temporaire, et déconnexion propre + email d'alerte à l'admin si Google rejette définitivement le refresh_token (accès révoqué, ou expiration au bout de 7 jours en mode "Testing" non publié). Toute la synchro passe désormais par cette nouvelle méthode ensure_valid_token().
* Ajout : avertissement dans Réglages > Google API expliquant le mode "Testing" de Google Cloud (tokens à 7 jours) et comment passer en Production pour des tokens permanents.
* Ajout : vérification temps réel des disponibilités via l'API Google FreeBusy (nouvelle méthode get_freebusy(), cache 2 minutes) en complément des RDV/créneaux bloqués déjà en base — utile pour un événement Google très récent que la synchro périodique n'a pas encore importé. Le formulaire client n'est jamais bloqué par un échec Google : au moindre souci, repli silencieux sur les données en base.
* Ajout : double vérification FreeBusy au moment de la réservation (book_appointment()), en plus du contrôle en base, pour éviter un double-booking de dernière minute.
* Ajout : statut détaillé du token de chaque commercial dans MP Agenda > Commerciaux (connecté avec date d'expiration, expiré avec renouvellement automatique annoncé, ou déconnecté avec bouton de reconnexion).
* Ajout : vérification quotidienne automatique des tokens (cron mp_agenda_google_token_check_cron) pour détecter une déconnexion Google avant qu'elle n'affecte un RDV.

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
