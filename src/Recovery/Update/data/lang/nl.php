<?php declare(strict_types=1);

return [
    'title' => 'Shopware 6 - Update Script',
    'meta_text' => '<strong>Shopware-Update:</strong>',

    'tab_start' => 'Actualisatie starten',
    'tab_check' => 'Systeemvereisten',
    'tab_migration' => 'Database migratie',
    'tab_cleanup' => 'Opruimen',
    'tab_done' => 'Gereed',

    'start_update' => 'Actualisatie uitvoeren',
    'configuration' => 'Configuratie',

    'back' => 'Terug',
    'forward' => 'Verder',
    'start' => 'Starten',

    'select_language' => 'Selecteer taal',
    'select_language_choose' => 'Kies taal',
    'select_language_de' => 'Duits',
    'select_language_en' => 'Engels',
    'select_language_nl' => 'Dutch',

    'noaccess_title' => 'Toegang geweigerd',
    'noaccess_info' => 'Voeg alstublieft uw IP adres toe "<strong>%s</strong>" de data <strong>%s</strong>',

    'step2_header_files' => 'Bestanden en Folders',
    'step2_files_info' => 'De volgende Bestanden en Folders moeten beschikbaar zijn en de juiste rechten bezitten',
    'step2_files_delete_info' => 'De volgende Folders moeten <strong>verwijderd</strong> worden.',
    'step2_error' => 'Sommige vereisten zijn niet vervuld',
    'step2_php_info' => 'Uw server moet de volgende systeemvereisten vervullen, voordat Shopware uit te voeren is.',
    'step2_system_colcheck' => 'Voorwaarde',
    'step2_system_colrequired' => 'Vereiste',
    'step2_system_colfound' => 'Uw Systeem',
    'step2_system_colstatus' => 'Status',

    'migration_progress_text' => 'Start u alstublieft uw Database-update met een klik op de knop "Starten"',
    'migration_header' => 'Database Update uitvoeren',
    'migration_counter_text_migrations' => 'Database Update word uitgevoerd',
    'migration_counter_text_snippets' => 'Tekstblokken worden geactualiseerd',
    'migration_counter_text_unpack' => 'Bestanden worden uitgepakt',
    'migration_update_success' => 'De update wordt succesvol uitgevoerd',

    'cleanup_header' => 'Opruimen',
    'cleanup_disclaimer' => 'De volgende bestanden behoren tot een oudere Shopware versie en worden na deze update niet langer geupdate. Drukt u op Verder om de bestanden automatisch te verwijderen en de update te beeindigen. Wij raden aan om een backup te creëren. <br /><strong>Afhankelijk van de hoeveelheid data, kan dit proces een bepaalde extra tijd duren</strong>',
    'cleanup_error' => 'De volgende bestanden kunnen niet verwijderd worden. U kunt deze manueel verwijderen, of zorg ervoor dat uw webserver genoeg rechten bezit om deze bestanden te verwijderen. Klik op de knop "Verder" om de update voort te zetten.',

    'done_title' => 'De update was succesvol!',
    'done_info' => 'Uw Shopware-installatie is succesvol geüpdatet.',
    'done_delete' => '<strong>Uw shop is momenteel in onderhoudsmodus.</strong><br/> Klik op "Update beëindigen" of verwijder de directory "/update-activa" handmatig om de update te beëindigen.',
    'done_frontend' => 'Naar het Storefront',
    'done_backend' => 'Naar het Administratie',
    'deleted_files' => '&nbsp;verwijderd bestanden uit %d directories',
    'cache_clear_error' => 'Fout opgetreden. De cache moet na het update handmatig worden vernieuwd.',

    'finish_update' => 'Update beëindigen',
];
