<?php declare(strict_types=1);
echo $app->getContainer()->get('renderer')->fetch('_header.php'); ?>

<div class="card__title">
    <h2><?= $t->t('language-selection_header'); ?></h2>
</div>

<form action="<?= $menuHelper->getNextUrl(); ?>" method="get">
    <div class="card__body is--align-center">

        <div class="welcome-illustration">
            <img src="<?= $baseUrl; ?>../assets/common/images/welcome.svg" alt="">
        </div>

        <div class="welcome-container">
            <h1 class="welcome-title"><?= $t->t('language-selection_welcome_title'); ?></h1>

            <p class="welcome-intro-message"><?= $t->t('language-selection_welcome_message'); ?></p>

            <input type="hidden" class="hidden-action" value="<?= $menuHelper->getCurrentUrl(); ?>" />

            <label for="language">
                <?= $t->t('language-selection_select_language'); ?>
                <a class="help-badge"
                   href="#"
                   data-tooltip="<?= $t->t('language-selection_info_message'); ?>">
                    <i class="icon-help"></i>
                </a>
            </label>

            <div class="select-wrapper language">
                <img class="language-flag"
                     src="<?= $baseUrl; ?>../assets/common/images/flags/<?= $selectedLanguage; ?>.png"
                     alt="<?= $selectedLanguage; ?>">
                <select id="language" name="language" class="language-selection">
                    <?php foreach ($languages as $language): ?>
                        <option value="<?= $language; ?>" <?= ($selectedLanguage === $language) ? 'selected' : ''; ?>>
                            <?= $t->t('select_language_' . $language); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card__footer flex-container">
        <button type="submit" class="btn btn-primary flex-item flex-right"><?= $t->t('forward'); ?></button>
    </div>
</form>

<?php echo $app->getContainer()->get('renderer')->fetch('_footer.php'); ?>
