<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $t->t('header_title'); ?> | Shopware 6</title>

    <link rel="icon" type="image/png" sizes="16x16" href="<?= $baseUrl; ?>../assets/common/images/favicon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $baseUrl; ?>../assets/common/images/favicon/favicon-32x32.png">
    <meta name="msapplication-TileColor" content="#189eff">
    <meta name="theme-color" content="#189eff">

    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/reset.css?v=<?= $version; ?>" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/fonts.css?v=<?= $version; ?>" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/icons.css?v=<?= $version; ?>" media="all"/>
    <link rel="stylesheet" type="text/css" href="<?= $baseUrl; ?>../assets/common/styles/style.css?v=<?= $version; ?>" media="all"/>

    <script>
        var shopwareTranslations = {
            'counterTextMigrations': '<?= $t->t('migration_counter_text_migrations'); ?>',
            'counterTextSnippets':   '<?= $t->t('migration_counter_text_snippets'); ?>',
            'updateSuccess':         '<?= $t->t('migration_update_success'); ?>'
        }
    </script>
</head>
<body>

<header class="header-main">
    <div class="header-main__branding">
        <img class="header-main__logo" src="<?= $baseUrl; ?>../assets/common/images/sw-logo-blue.svg" width="148" alt="Shopware">
        <div class="header-main__title">
            <?= $t->t('header_title'); ?>
        </div>
    </div>

    <div class="version--notice">
        <?= $t->t('version_text'); ?> <?= $version; ?>
    </div>
</header>

<div class="page--wrap">

    <div class="content--wrapper">
        <?php $menuHelper->printMenu(); ?>
        <section class="content--main">
