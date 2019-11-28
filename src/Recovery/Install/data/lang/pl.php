<?php declare(strict_types=1);

return [
    'menuitem_language-selection' => 'Start',
    'menuitem_requirements' => 'Wymagania systemowe',
    'menuitem_database-configuration' => 'Konfiguracja bazy danych',
    'menuitem_database-import' => 'Instalacja',
    'menuitem_edition' => 'Licencja',
    'menuitem_configuration' => 'Konfiguracja',
    'menuitem_finish' => 'Koniec',
    'menuitem_license' => 'Warunki świadczenia usług',

    'license_incorrect' => 'The licence key entered does not appear to be valid',
    'license_does_not_match' => 'The licence key entered does not match a commercial Shopware version',
    'license_domain_error' => 'The licence key entered is not valid for the domain: ',

    'version_text' => 'Wersja:',
    'back' => 'Wstecz',
    'forward' => 'Dalej',
    'start' => 'Start',
    'start_installation' => 'Rozpocznij instalację',

    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',
    'select_language_it' => 'Italiano',
    'select_language_fr' => 'Français',
    'select_language_es' => 'Español',
    'select_language_pt' => 'Português',
    'select_language_pl' => 'Polski',

    'language-selection_select_language' => 'Język kreatora instalacji',
    'language-selection_header' => 'Twoja instalacja Shopware',
    'language-selection_info_message' => 'Wybrany tutaj język dotyczy tylko kreatora instalacji. Możesz później określić język swojego sklepu.',
    'language-selection_welcome_message' => <<<'EOT'
<p>
    Cieszymy się, że chcesz dołączyć do naszej fantastycznej, globalnej społeczności Shopware.
</p>
<p>
    Teraz krok po kroku przeprowadzimy Cię przez proces instalacji, Jeśli masz jakieś pytania, po prostu zaglądnij na nasze  <a href="https://forum.shopware.com" target="_blank">forum</a>, zadzwoń na <a href="tel:0080074676260">00 800 746 7626 0 (bezpłatnie)</a> lub wyślij nam <a href="mailto:info@shopware.com">e-mail</a>.
</p>
<p>
    <strong>Zaczynajmy</strong>
</p>
EOT
    ,
    'requirements_header' => 'Wymagania systemowe',
    'requirements_header_files' => 'Pliki i katalogi',
    'requirements_header_system' => 'System',
    'requirements_files_info' => 'Następujące pliki i katalogi muszą istnieć na serwerze i mieć uprawnienia do zapisu',
    'requirements_tablefiles_colcheck' => 'Plik/katalog',
    'requirements_tablefiles_colstatus' => 'Status',
    'requirements_error' => '<h3 class="alert-heading">Uwaga!</h3>Nie wszystkie wymagania zostały spełnione',
    'requirements_success' => '<h3 class="alert-heading">Gratulacje!</h3>Wszystkie wymagania zostały spełnione',
    'requirements_php_info' => 'Twój serwer musi spełniać następujące wymagania systemowe, aby móc uruchomić Shopware',
    'requirements_php_max_compatible_version' => 'Ta wersja od Shopware obsługuje do wersji %s. Pełna funkcjonalność z nowszymi wersjami PHP nie może być zagwarantowana.',
    'requirements_system_colcheck' => 'Zelażność',
    'requirements_system_colrequired' => 'Wymagane',
    'requirements_system_colfound' => 'Obecnie',
    'requirements_system_colstatus' => 'Status',
    'requirements_show_all' => '(pokaż wszystkie)',
    'requirements_hide_all' => '(ukryj wszystkie)',

    'license_agreement_header' => 'Ogólne Warunki Świadczenia Usług',
    'license_agreement_info' => 'Tutaj znajdziesz podsumowanie naszych warunków korzystania z usługi, które musisz przeczytać i zaakceptować, aby móc zakończyć instalację. Wersja Shopware Community Edition jest licencjonowana na zasadach licencji AGPL, natomiast części wtyczek i szablonu są objęte nową licencją BSD.',
    'license_agreement_error' => 'Musisz zaakceptować nasze warunki',
    'license_agreement_checkbox' => 'Akceptuję powyższe warunki świadczenia usług',

    'database-configuration_header' => 'Konfiguracja bazy danych',
    'database-configuration_field_host' => 'Serwer bazy danych:',
    'database-configuration_advanced_settings' => 'Pokaż opcje zaawansowane',
    'database-configuration_field_port' => 'Port:',
    'database-configuration_field_socket' => 'Socket (opcjonalnie):',
    'database-configuration_field_user' => 'Użytkownik bazy danych:',
    'database-configuration_field_password' => 'Hasło:',
    'database-configuration_field_database' => 'Nazwa bazy danych:',
    'database-configuration_field_new_database' => 'New database:',
    'database-configuration_info' => 'Dane dostępu do bazy danych są wymagane w celu zainstalowania Shopware w twoim systemie. Jeśli nie masz pewności, co wpisać, skontaktuj się z administratorem / dostawcą usług hostingowych.',
    'database-configuration-create_new_database' => 'Utwórz nową bazę danych',

    'database-import_header' => 'Instalacja',
    'database-import_skip_import' => 'Pomiń',
    'database-import_progress' => 'Postęp: ',
    'database-import-hint' => '<strong>Uwaga: </strong> Jeśli w wybranej bazie danych istnieją już tabele Shopware, zostaną one usunięte podczas instalacji / aktualizacji!',
    'migration_counter_text_migrations' => 'Aktualizacja bazy danych',
    'migration_counter_text_snippets' => 'Aktualizowanie modułów tekstowych',
    'migration_update_success' => 'Baza danych pomyślnie zaimportowana!',

    'edition_header' => 'Kupiłeś licencję Shopware?',
    'edition_info' => 'Shopware jest dostępny za darmo w wersji <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition</a>, i jest również dostępny jako <a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus lub Enterprise Edition</a>, za jednorazową opłatą.',
    'edition_ce' => 'Nie, chciałbym skorzystać z bezpłatnego <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition</a>.',
    'edition_cm' => 'Tak, kupiłem licencję na oprogramowanie typu Shopware (<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus lub Enterprise</a>).',
    'edition_license' => 'Wprowadź tutaj swój klucz licencyjny. Możesz go znaleźć na swoim koncie Shopware w sekcji "Licences" &rarr; "Product licences" &rarr; "Details / Download":',
    'edition_license_error' => 'Aby zainstalować płatną wersję Shopware, niezbędne jest posiadanie ważnej licencji.',

    'configuration_header' => 'Podstawowa konfiguracja sklepu',
    'configuration_sconfig_text' => 'Prawie skończone! Teraz wystarczy wprowadzić kilka podstawowych ustawień dla swojego sklepu, a instalacja zostanie zakończona. Wszystko, co tu wpiszesz, może zostać później zmienione.',
    'configuration_sconfig_name' => 'Nazwa Twojego sklepu:',
    'configuration_sconfig_name_info' => 'Proszę wpisać naswę swojego sklepu',
    'configuration_sconfig_mail' => 'Adres E-mail sklepu:',
    'configuration_sconfig_mail_info' => 'Proszę podać adres email dla wychodzących wiadomości',
    'configuration_sconfig_domain' => 'Domena sklepu:',
    'configuration_sconfig_language' => 'Główny język:',
    'configuration_sconfig_currency' => 'Standardowa waluta:',
    'configuration_sconfig_currency_info' => 'Ta waluta będzie używana jako standardowa przy ustalaniu cen produktów',
    'configuration_admin_currency_eur' => 'Euro',
    'configuration_admin_currency_usd' => 'Dolar (US)',
    'configuration_admin_currency_gbp' => 'Szterling (UK)',
    'configuration_admin_username' => 'Login administratora:',
    'configuration_admin_mail' => 'E-mail administratora:',
    'configuration_admin_firstName' => 'first name:',
    'configuration_admin_lastName' => 'last name:',

    'configuration_admin_language_de' => 'Niemiecki',
    'configuration_admin_language_en' => 'Angielski',
    'configuration_admin_password' => 'Hasło administratora:',

    'finish_header' => 'Instalacja zakończona',
    'finish_info' => 'Pomyślnie zainstalowałeś Shopware!',
    'finish_info_heading' => 'Hurra!',
    'finish_first_steps' => '"Przewodnik "Pierwsze kroki"',
    'finish_frontend' => 'Idź do frontendu sklepu',
    'finish_backend' => 'Idź do backendu sklepu (administracja)',
    'finish_message' => '
<p>
    <strong>Witamy w Shopware,</strong>
</p>
<p>
    Z radością witamy Cię w naszej społeczności. Pomyślnie zainstalowałeś Shopware.
<p>Twój sklep jest gotowy do użycia. Jeśli jesteś nowym użytkownikiem Shopware, zalecamy zapoznanie się z przewodnikiem <a href="https://docs.shopware.com/en/shopware-5-en/first-steps/first-steps-in-shopware" target="_blank">"Pierwsze kroki w Shopware"</a>. Kiedy zalogujesz się do sklepu po raz pierwszy, nasz "Kreator pierwszego uruchomienia" poprowadzi cię przez kolejne podstawowe ustawienia.</p>
<p>Ciesz się swoim nowym sklepem internetowym!</p>',
];
