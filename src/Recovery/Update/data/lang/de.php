<?php declare(strict_types=1);

return [
    'title' => 'Shopware 6 - Update Script',
    'meta_text' => '<strong>Shopware-Update:</strong>',

    'tab_start' => 'Aktualisierung starten',
    'tab_check' => 'Systemvoraussetzungen',
    'tab_migration' => 'Datenbank Migration',
    'tab_cleanup' => 'Aufräumen',
    'tab_done' => 'Fertig',

    'start_update' => 'Aktualisierung starten',
    'configuration' => 'Konfiguration',

    'back' => 'Zurück',
    'forward' => 'Weiter',
    'start' => 'Starten',

    'select_language' => 'Sprache wählen',
    'select_language_choose' => 'Bitte wählen',
    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',

    'noaccess_title' => 'Zugang Verweigert',
    'noaccess_info' => 'Bitte fügen Sie Ihre IP-Adresse "<strong>%s</strong>" der Datei <strong>%s</strong> hinzu.',

    'step2_header_files' => 'Dateien und Verzeichnisse',
    'step2_files_info' => 'Die nachfolgenden Dateien und Verzeichnisse müssen vorhanden sein und Schreibrechte besitzen',
    'step2_files_delete_info' => 'Die nachfolgenden Verzeichnisse müssen <strong>gelöscht</strong> sein.',
    'step2_error' => 'Einige Voraussetzungen werden nicht erfüllt',
    'step2_php_info' => 'Ihr Server muss die folgenden Systemvoraussetzungen erfüllen, damit Shopware lauffähig ist',
    'step2_system_colcheck' => 'Voraussetzung',
    'step2_system_colrequired' => 'Erforderlich',
    'step2_system_colfound' => 'Ihr System',
    'step2_system_colstatus' => 'Status',

    'migration_progress_text' => 'Bitte starten Sie das Datenbank-Update mit einen Klick auf den Button "Starten"',
    'migration_header' => 'Datenbank Update durchführen',
    'migration_counter_text_migrations' => 'Datenbank-Update wird durchgeführt',
    'migration_counter_text_snippets' => 'Textbausteine werden aktualisiert',
    'migration_counter_text_unpack' => 'Dateien werden überschrieben',
    'migration_update_success' => 'Das Update wurde erfolgreich durchgeführt',

    'cleanup_header' => 'Aufräumen',
    'cleanup_dir_table_header' => 'Verzeichnis / Datei',
    'cleanup_disclaimer' => 'Die folgenden Dateien gehören zu einer früheren Shopware Version und werden nach diesem Update nicht länger benötigt. Drücken Sie "Weiter" um die Dateien automatisch zu löschen und das Update zu beenden. Wir empfehlen vorher ein Backup anzulegen. <strong>Abhänging von der Menge der aufzuräumenden Dateien kann dieser Prozess einige Zeit in Anspruch nehmen.</strong>',
    'cleanup_error' => 'Die folgenden Dateien konnten nicht gelöscht werden. Bitte löschen Sie dieser per Hand, oder stellen Sie sicher, dass Ihr Webserver genug Rechte besitzt diese Dateien zu löschen. Drücken Sie "Weiter" um den Update Vorgang fortzusetzen.',

    'done_title' => 'Das Update war erfolgreich!',
    'done_info' => 'Deine Shopware-Installation wurde erfolgreich aktualisiert.',
    'done_delete' => '<strong>Ihr Shop befindet sich zurzeit im Wartungsmodus.</strong><br/>Bitte klick auf "Update abschließen" oder lösche den Ordner "/update-assets" manuell, um das Update abzuschließen.',
    'done_frontend' => 'Zur Storefront',
    'done_backend' => 'Zur Administration',
    'deleted_files' => '&nbsp;entfernte Dateien aus %d Verzeichnissen',
    'cache_clear_error' => 'Es ist ein Fehler aufgetreten. Bitte löschen Sie den Cache nach dem Update manuell.',

    'finish_update' => 'Update abschließen',
];
