<?php declare(strict_types=1);
echo $renderer->fetch('_header.php', ['tab' => 'dbmigration']); ?>

<div class="card__title">
    <h2><?= $language['migration_header']; ?></h2>
</div>

<div class="card__body">
    <div style="display: none;" class="alert alert-error">
        &nbsp;
    </div>

    <div class="progress-container">
        <div class="progress">
            <div class="progress-bar"></div>
        </div>

        <div class="counter-text is--hidden">
            <strong class="counter-numbers">&nbsp;</strong>
            <p class="counter-content">
                &nbsp;
            </p>
        </div>

        <div class="progress-text is--hidden">
            <?= $language['migration_progress_text']; ?>
        </div>

        <div class="progress-actions">

        </div>
    </div>
</div>

<form class="card__footer" action="<?= $router->pathFor('cleanup'); ?>" method="get">
    <div class="flex-container">
        <div class="progress-actions flex-item">
            <input type="submit" class="btn btn-primary btn-arrow-right is--hidden" id="forward-button" value="<?= $language['forward']; ?>"" />
        </div>

        <a href="#" id="start-ajax" class="btn btn-primary btn-arrow-right flex-item flex-right"><?= $language['start']; ?></a>
    </div>
</form>

<?php echo $renderer->fetch('_footer.php'); ?>
