<?php declare(strict_types=1);
echo $renderer->fetch('_header.php', ['tab' => 'system']); ?>

<div class="card__title">
    <h2><?= $language['step2_header_files']; ?></h2>
</div>

<div class="card__body">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= $language['step2_error']; ?>
        </div>
    <?php endif; ?>

    <p>
        <?= $language['step2_files_info']; ?>
    </p>

    <table class="table table-striped">
        <tbody>
            <?php foreach ($systemCheckResultsWritePermissions as $systemCheckResult): ?>
                <?php $class = ($systemCheckResult['result']) ? 'success' : 'error'; ?>
                <tr class="<?= $class; ?>">
                    <td><?= $systemCheckResult['name']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form action="<?= $router->pathFor('checks'); ?>" method="get" class="card__footer flex-container">
    <a href="<?= $router->pathFor('welcome'); ?>" class="btn btn-default flex-item"><?= $language['back']; ?></a>
    <button type="submit" class="btn btn-primary flex-item flex-right"><?= $language['forward']; ?></button>
</form>

<?php echo $renderer->fetch('_footer.php'); ?>
