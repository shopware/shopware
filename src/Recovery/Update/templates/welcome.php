<?php declare(strict_types=1);
echo $renderer->fetch('_header.php', ['tab' => 'start']); ?>

<div class="card__title">
    <h2><?= $language['start_update']; ?></h2>
</div>

<form action="<?= $router->pathFor('checks'); ?>" method="POST">
    <div class="card__body">

        <input type="hidden" class="hidden-action" value="<?= $router->pathFor('welcome'); ?>" />

        <label for="language"><?= $language['select_language']; ?></label>

        <div class="select-wrapper language">
            <img class="language-flag"
                 src="<?= $baseUrl; ?>../assets/common/images/flags/<?= $selectedLanguage; ?>.png"
                 alt="<?= $selectedLanguage; ?>">
            <select name="language" id="language" class="language-selection">
                <option value="de"<?php if ($selectedLanguage === 'de') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_de']; ?>
                </option>
                <option value="en"<?php if ($selectedLanguage === 'en') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_en']; ?>
                </option>
                <option value="cz"<?php if ($selectedLanguage === 'cz') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_cs']; ?>
                </option>
                <option value="es"<?php if ($selectedLanguage === 'es') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_es']; ?>
                </option>
                <option value="fr"<?php if ($selectedLanguage === 'fr') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_fr']; ?>
                </option>
                <option value="it"<?php if ($selectedLanguage === 'it') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_it']; ?>
                </option>
                <option value="nl"<?php if ($selectedLanguage === 'nl') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_nl']; ?>
                </option>
                <option value="pt"<?php if ($selectedLanguage === 'pt') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_pt']; ?>
                </option>
                <option value="sv"<?php if ($selectedLanguage === 'sv') : ?> selected="selected"<?php endif; ?>>
                    <?= $language['select_language_sv']; ?>
                </option>
            </select>
        </div>
    </div>

    <div class="card__footer flex-container">
        <button type="submit" class="btn btn-primary flex-item flex-right">
            <?= $language['forward']; ?>
        </button>
    </div>
</form>

<?php echo $renderer->fetch('_footer.php'); ?>
