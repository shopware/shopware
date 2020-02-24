<?php declare(strict_types=1);

return [
    'title' => 'Shopware 6 - Update Script',
    'meta_text' => '<strong>Shopware update:</strong>',

    'tab_start' => 'Start update',
    'tab_check' => 'System requirements',
    'tab_migration' => 'Database migration',
    'tab_cleanup' => 'Cleanup',
    'tab_done' => 'Done',

    'start_update' => 'Start update',
    'configuration' => 'Configuration',

    'back' => 'Back',
    'forward' => 'Forward',
    'start' => 'Start',

    'select_language' => 'Select language',
    'select_language_choose' => 'Please choose',
    'select_language_de' => 'German',
    'select_language_en' => 'English',
    'select_language_nl' => 'Dutch',
    'select_language_it' => 'Italian',
    'select_language_fr' => 'French',
    'select_language_es' => 'Spanish',
    'select_language_pt' => 'Portuguese',
    'select_language_pl' => 'Polish',
    'select_language_cs' => 'Czech',
    'select_language_sv' => 'Swedish',

    'noaccess_title' => 'Access denied',
    'noaccess_info' => 'Please add your IP address "<strong>%s</strong>" to the <strong>%s</strong> file to enable access.',

    'step2_header_files' => 'File & directory permissions',
    'step2_files_info' => 'The following files and directories must exist and be writable',
    'step2_files_delete_info' => 'The following directories have to be <strong>deleted</strong>',
    'step2_error' => 'Some system requirements are not met',
    'step2_php_info' => 'Your server must meet the following requirements in order to run Shopware',
    'step2_system_colcheck' => 'Check',
    'step2_system_colrequired' => 'Required',
    'step2_system_colfound' => 'Found',
    'step2_system_colstatus' => 'Status',

    'migration_progress_text' => 'Please start the database migration process by clicking the "Start" button',
    'migration_header' => 'Database migration',
    'migration_counter_text_migrations' => 'Database migration in progress',
    'migration_counter_text_snippets' => 'Updating snippets',
    'migration_counter_text_unpack' => 'Updating files',
    'migration_update_success' => 'Update complete',

    'cleanup_header' => 'File cleanup',
    'cleanup_dir_table_header' => 'Directory / File',
    'cleanup_disclaimer' => 'The following files belong to your previous Shopware version, but are no longer necessary after this update. Press "Forward" to remove them automatically and finish the update process. We recommend performing a backup before proceeding. <strong>Depending on the amount of files this process may take some time.</strong>',
    'cleanup_error' => 'The following files could not be deleted. Please delete them manually or ensure that your web server\'s user profile has sufficient permissions to do so. Click "Forward" to resume the update process.',

    'done_title' => 'The update was successful!',
    'done_info' => 'Your Shopware installation has been successfully updated.',
    'done_delete' => '<strong>Your shop is currently in maintenance mode.</strong><br/> Click "Finish Update" or delete the directory "/update-assets" manually to finish the update.',
    'done_frontend' => 'Open shop storefront',
    'done_backend' => 'Open shop administration',
    'deleted_files' => '&nbsp;deleted files from %d directories',
    'cache_clear_error' => 'An error occurred. Please delete the cache manually after finishing the update.',

    'finish_update' => 'Finish update',
];
