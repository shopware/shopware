<?php declare(strict_types=1);
echo $app->getContainer()->get('renderer')->fetch('_header.php'); ?>

<div class="card__title">
    <h2><?= $t->t('license_agreement_header'); ?></h2>
</div>

<form action="<?= $menuHelper->getCurrentUrl(); ?>" method="post">
    <div class="card__body">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $t->t('license_agreement_error'); ?>
            </div>
        <?php endif; ?>

        <p>
            <?= $t->t('license_agreement_info'); ?>
        </p>

        <div id="tos" class="license-agreement"><?= $tos; ?></div>

        <div class="custom-checkbox">
            <input type="checkbox" id="terms" name="tos" required/>
            <label for="terms"><?= $t->t('license_agreement_checkbox'); ?></label>
        </div>
    </div>
    <div class="card__footer flex-container">
        <a href="<?= $menuHelper->getPreviousUrl(); ?>" class="btn btn-default flex-item"><?= $t->t('back'); ?></a>
        <button type="submit" class="btn btn-primary flex-item flex-right"><?= $t->t('forward'); ?></button>
    </div>
</form>

<?php echo $app->getContainer()->get('renderer')->fetch('_footer.php'); ?>
