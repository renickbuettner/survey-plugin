<?php return [
    'plugin' => [
        'name' => 'Survey',
        'description' => 'Plugin für Umfragen und Nutzerinteraktionen',
    ],
    'survey' => [
        'created_at' => 'Erstellt am',
        'updated_at' => 'Aktualisiert am',
        'deleted_at' => 'Gelöscht am',
        'title' => 'Titel der Umfrage',
        'description' => 'Beschreibung der Umfrage',
        'is_active' => 'Für Antworten offen?',
        'is_anonym' => 'Anonyme Umfrage?',
        'is_multiselect' => 'Mehrere Antworten gestattet?',
        'options' => 'Antwortmöglichkeiten',
        'notification_to' => 'E-Mail Adresse für Benachrichtigungen',
        'meta' => 'Umfrage Meta Daten',
        'statistics' => 'Statistiken',
        'no_statistics' => 'Keine Statistiken vorhanden',
        'statistics_event_count' => 'Teilnehmende',
        'statistics_choices_count' => 'Antworten',
        'tab_general' => 'Allgemein',
        'tab_options' => 'Antwortmöglichkeiten',
        'option_title' => 'Titel',
        'option_text' => 'Text',
        'option_text_hint' => 'Dieser Text wird als Antwortmöglichkeit inkl. Formatierungen angezeigt.',
    ],
    'component' => [
        'name' => 'Umfrage',
        'description' => 'Fügt eine Umfrage auf der Seite hinzu',
        'submit' => 'Jetzt senden',
        'total_choices' => 'Aktuelle Auswertung',
        'error_invalid_request' => 'Ungültige Anfrage',
        'error_already_submitted' => 'Sie haben bereits an dieser Umfrage teilgenommen.',
        'feedback_success' => 'Vielen Dank für Ihre Teilnahme!',
        'user_details' => 'Persönliche Angaben',
        'user_name' => 'Ihr Name',
        'user_email' => 'Ihre E-Mail Adresse',
        'user_phone' => 'Ihre Rufnummer',
        'user_comment' => 'Ihre Anmerkung',
        'user_privacy' => 'Datenschutz akzeptiert',
        'user_privacy_notice' => 'Sie sind mit unserer <a href="/datenschutz">Datenschutzerklärung</a> einverstanden, und stimmen zu, dass Ihre Angaben zur Kontaktaufnahme und für Rückfragen zweckbezogen gespeichert werden.',
    ],
    'permissions' => [
        'survey_manage' => 'Umfragen verwalten?',
    ],
];
