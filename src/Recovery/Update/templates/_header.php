<!doctype html>

<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $language['title']; ?></title>

    <link rel="shortcut icon" href="<?= $baseUrl; ?>../assets/common/images/favicon.ico" type="image/x-icon" />

    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/reset.css" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/fonts.css" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/icons.css" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/style.css?<?= $version; ?>" media="all"/>

    <script>
        var shopwareTranslations = {
            'counterTextUnpack':     '<?= $language['migration_counter_text_unpack']; ?>',
            'counterTextMigrations': '<?= $language['migration_counter_text_migrations']; ?>',
            'counterTextSnippets':   '<?= $language['migration_counter_text_snippets']; ?>',
            'updateSuccess':         '<?= $language['migration_update_success']; ?>'
        }
    </script>
</head>

<body class="<?= (!UPDATE_IS_MANUAL && $tab === 'dbmigration' || !UPDATE_IS_MANUAL && $tab === 'done') ? 'auto' : ''; ?>">

    <!-- Header -->
    <header class="header-main">
        <div class="header-main__branding">
            <img class="header-main__logo" src="<?= $baseUrl; ?>../assets/common/images/sw-logo-blue.svg" width="148" alt="Shopware">
            <div class="header-main__title">
                UPDATE
            </div>
        </div>

        <div class="version--notice">
            <?= $version; ?>
        </div>
    </header>

    <div class="page--wrap">

    <div class="content--wrapper">
        <!-- Navigation list -->
        <nav class="navigation--main">
            <ul class="navigation--list">
                <li class="navigation--entry <?= ($tab === 'start') ? 'is--active' : ''; ?>">
                    <span class="navigation--link"><?= $language['tab_start']; ?></span>
                </li>

                <li class="navigation--entry  <?= ($tab === 'system') ? 'is--active' : ''; ?>">
                    <span class="navigation--link"><?= $language['tab_check']; ?></span>
                </li>

                <li class="navigation--entry  <?= ($tab === 'dbmigration') ? 'is--active' : ''; ?>">
                    <span class="navigation--link"><?= $language['tab_migration']; ?></span>
                </li>

                <li class="navigation--entry <?= ($tab === 'cleanup') ? 'is--active' : ''; ?>">
                    <span class="navigation--link"><?= $language['tab_cleanup']; ?></span>
                </li>

                <li class="navigation--entry <?= ($tab === 'done') ? 'is--active' : ''; ?>">
                    <span class="navigation--link"><?= $language['tab_done']; ?></span>
                </li>
            </ul>
        </nav>
        <section class="content--main">
