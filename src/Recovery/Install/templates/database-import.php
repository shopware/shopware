<?php declare(strict_types=1);
echo $app->getContainer()->get('renderer')->fetch('_header.php'); ?>

<div class="card__title">
    <h2><?= $t->t('database-import_header'); ?></h2>
</div>

<div class="card__body database-import">
    <?php if ($error): ?>
        <div class="alert-hero error">
            <div class="alert-hero-icon">
                <i class="icon-warning"></i>
            </div>
            <h3 class="alert-hero-title"><?= $t->t('database_import_error_title'); ?></h3>
            <div class="alert-hero-text"><?= $error; ?></div>
        </div>
    <?php endif; ?>

    <div style="display: none;" class="alert alert-error">
        &nbsp;
    </div>

    <div class="database-import-container">
        <p class="database-import-text"><?= $t->t('database-import_info_text'); ?></p>

        <div class="database-import-count">
            <?= $t->t('database_import_install_label'); ?> <?= $t->t('database_import_install_step_text'); ?>
            <span class="database-import-count-offset">0</span>
            <?= $t->t('database_import_install_from_text'); ?>
            <span class="database-import-count-total">0</span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: 0%"></div>
        </div>
    </div>

    <div class="database-import-finish is--hidden">
        <div class="alert-hero success">
            <div class="alert-hero-icon">
                <i class="icon-checkmark"></i>
            </div>
            <h3 class="alert-hero-title"><?= $t->t('database_import_success'); ?></h3>
        </div>
    </div>
</div>

<div class="card__footer flex-container">
    <a href="<?= $menuHelper->getPreviousUrl(); ?>" id="back" class="btn btn-default flex-item"><?= $t->t('back'); ?></a>
    <a href="<?= $menuHelper->getNextUrl(); ?>" class="btn btn-primary flex-item flex-right is--hidden"><?= $t->t('forward'); ?></a>

    <button id="start-ajax" class="btn btn-primary btn-start-installation flex-item flex-right"><?= $t->t('forward'); ?></button>
</div>


<?php echo $app->getContainer()->get('renderer')->fetch('_footer.php'); ?>
