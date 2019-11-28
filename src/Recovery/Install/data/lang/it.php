<?php declare(strict_types=1);

return [
    'menuitem_language-selection' => 'Start',
    'menuitem_requirements' => 'Requisiti del sistema',
    'menuitem_database-configuration' => 'Configurazione banca dati',
    'menuitem_database-import' => 'Installazione',
    'menuitem_edition' => 'Licenza Shopware',
    'menuitem_configuration' => 'Configurazione',
    'menuitem_finish' => 'Pronto',
    'menuitem_license' => 'Condizioni generali',

    'license_incorrect' => 'Il codice di licenza immesso sembra non essere valido',
    'license_does_not_match' => 'Il codice di licenza immesso non corrisponde a nessuna versione commerciale di Shopware',
    'license_domain_error' => 'Il codice di licenza immesso non è valido per il dominio: ',

    'version_text' => 'Versione:',
    'back' => 'Indietro',
    'forward' => 'Avanti',
    'start' => 'Avvia',
    'start_installation' => 'Avvia installazione',

    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',
    'select_language_it' => 'Italiano',
    'select_language_fr' => 'Français',
    'select_language_es' => 'Español',
    'select_language_pt' => 'Português',
    'select_language_pl' => 'Polski',

    'language-selection_select_language' => 'Assistente installazione lingua',
    'language-selection_header' => 'Installazione del tuo Shopware',
    'language-selection_info_message' => 'La lingua da selezionare qui si riferisce soltanto all\'assistente all\'installazione; la lingua dello Shop potrà essere definita successivamente.',
    'language-selection_welcome_message' => <<<EOT
<p>
    Siamo lieti che tu voglia entrare a far parte della nostra grande Shopware Community mondiale.
</p>
<p>
    Ti accompagniamo ora passo passo nel processo di installazione. Per qualsiasi domanda, consulta il nostro <a href="https://forum.shopware.com" target="_blank">Forum</a> o contattaci telefonicamente al numero <a href="tel:0080074676260">00 800 746 7626 0 (gratis)</a> o inviaci una <a href="mailto:info@shopware.com">e-mail</a>.
</p>
<p>
    <strong>Si parte</strong>
</p>
EOT
    ,
    'requirements_header' => 'Requisiti del sistema',
    'requirements_header_files' => 'File e directory',
    'requirements_header_system' => 'Sistema',
    'requirements_files_info' => 'I file e le directory seguenti devono essere presenti e con diritto di scrittura',
    'requirements_tablefiles_colcheck' => 'File/Direcoty',
    'requirements_tablefiles_colstatus' => 'Stato',
    'requirements_error' => '<h3 class="alert-heading">Attenzione!</h3>Non tutti i requisiti per l\'installazione sono soddisfatti',
    'requirements_success' => '<h3 class="alert-heading">Esito positivo!</h3>Tutti i requisiti per l\'installazione sono soddisfatti',
    'requirements_php_info' => 'Per consentire l\'utilizzo di Shopware, il tuo server deve soddisfare i requisiti di sistema seguenti.',
    'requirements_php_max_compatible_version' => 'Questa versione di Shopware supporta PHP fino alla versione %s. Non è possibile garantire la piena funzionalità con le versioni più recenti di PHP.',
    'requirements_system_colcheck' => 'Requisito',
    'requirements_system_colrequired' => 'Obbligatorio',
    'requirements_system_colfound' => 'Il tuo sistema',
    'requirements_system_colstatus' => 'Stato',
    'requirements_show_all' => '(mostra tutto)',
    'requirements_hide_all' => '(nascondi tutto)',

    'license_agreement_header' => 'Condizioni generali ("CG")',
    'license_agreement_info' => 'Qui sono indicate le nostre condizioni generali che devono essere lette e accettate per portare a termine l\'installazione. Shopware Community Edition è soggetto a licenza AGPL, mentre i componenti dei plugin e il template sono soggetti a licenza New BSD.',
    'license_agreement_error' => 'Accetta le nostre condizioni generali',
    'license_agreement_checkbox' => 'Accetto le condizioni generali',

    'database-configuration_header' => 'Configura banca dati',
    'database-configuration_field_host' => 'Server banca dati:',
    'database-configuration_advanced_settings' => 'Mostra impostazioni avanzate',
    'database-configuration_field_port' => 'Porta banca dati:',
    'database-configuration_field_socket' => 'Socket banca dati (facoltativo):',
    'database-configuration_field_user' => 'Utente banca dati:',
    'database-configuration_field_password' => 'Password banca dati:',
    'database-configuration_field_database' => 'Nome banca dati:',
    'database-configuration_field_new_database' => 'New database:',
    'database-configuration_info' => 'Per installare Shopware sul tuo sistema, devi immettere i dati di accesso alla banca dati. Se non sei sicuro dei dati da immettere, contatta il tuo amministratore / provider di servizi di hosting.',
    'database-configuration-create_new_database' => 'Crea nuova banca dati',

    'database-import_header' => 'Installazione',
    'database-import_skip_import' => 'Salta',
    'database-import_progress' => 'Stato: ',
    'database-import-hint' => '<strong>Nota: </strong> Se nella banca dati configurata esistono già tabelle Shopware, queste saranno rimosse al momento dell\'installazione/aggiornamento!',
    'migration_counter_text_migrations' => 'Aggiornamento banca dati eseguito',
    'migration_counter_text_snippets' => 'Aggiornamento testi automatici in corso',
    'migration_update_success' => 'Importazione della banca dati riuscita!',

    'edition_header' => 'Hai acquistato una licenza Shopware?',
    'edition_info' => 'Shopware è disponibile in una <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition </a> gratuita e nelle edizioni<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus ed Enterprise</a>.',
    'edition_ce' => 'No, desidero utilizzare una <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition</a> gratuita.',
    'edition_cm' => 'Sì, ho una licenza Shopware a pagamento (<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus o Enterprise</a>).',
    'edition_license' => 'Immetti qui il tuo codice di licenza. Puoi trovarlo nel tuo account Shopware in "Licenze" &rarr; "Licenze prodotto" &rarr; "Dettagli / Download":',
    'edition_license_error' => 'Per l\'installazione di una versione Shopware a pagamento, è necessaria una licenza valida.',

    'configuration_header' => 'Configurazione base Shop',
    'configuration_sconfig_text' => 'Quasi fatto! Per concludere l\'installazione, devi ancora definire soltanto alcune impostazioni base per il tuo Shop. Ovviamente potrai modificare successivamente tutto quello che immetti qui.',
    'configuration_sconfig_name' => 'Nome del tuo Shop:',
    'configuration_sconfig_name_info' => 'Indica il nome del tuo Shop',
    'configuration_sconfig_mail' => 'Indirizzo e-mail dello Shop:',
    'configuration_sconfig_mail_info' => 'Indica un indirizzo e-mail per la posta in uscita',
    'configuration_sconfig_domain' => 'Dominio Shop:',
    'configuration_sconfig_language' => 'Lingua principale:',
    'configuration_sconfig_currency' => 'Valuta standard:',
    'configuration_sconfig_currency_info' => 'Questa è la valuta standard utilizzata per definire i prezzi degli articoli',
    'configuration_admin_currency_eur' => 'Euro',
    'configuration_admin_currency_usd' => 'Dollaro (US)',
    'configuration_admin_currency_gbp' => 'Sterlina britannica (GB)',
    'configuration_admin_username' => 'Nome login amministratore:',
    'configuration_admin_mail' => 'e-mail amministratore:',
    'configuration_admin_firstName' => 'first name:',
    'configuration_admin_lastName' => 'last name:',

    'configuration_admin_language_de' => 'Tedesco',
    'configuration_admin_language_en' => 'Inglese',
    'configuration_admin_password' => 'Password amministratore:',

    'finish_header' => 'Installazione conclusa',
    'finish_info' => 'Shopware è stato installato con successo!',
    'finish_info_heading' => 'Evviva!',
    'finish_first_steps' => 'Guida "Primi passi"',
    'finish_frontend' => 'Vai a Shop Front-end',
    'finish_backend' => 'Vai a Shop Back-end (amministrazione)',
    'finish_message' => '
<p>
    <strong>Benvenuto in Shopware,</strong>
</p>
<p>
    siamo lieti di darti il benvenuto nella nostra Community. Shopware è stato installato con successo.
<p>Il tuo Shop ora è pronto all\'uso. Se sei nuovo in Shopware, ti suggeriamo di leggere la guida <a href="https://docs.shopware.com/en/shopware-5-en/first-steps/first-steps-in-shopware" target="_blank">"Primi passi in Shopware"</a>. Se stai effettuando il primo accesso allo Shop Backend, la nostra procedura guidata "First Run" ti spiega i passaggi principali da compiere.</p>
<p>Buon divertimento con il tuo nuovo Onlineshop!</p>',
];
