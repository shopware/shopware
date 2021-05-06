<?php declare(strict_types=1);
echo $renderer->fetch('_header.php', ['tab' => 'cleanup']); ?>

<div class="card__title">
    <h2><?= $language['cleanup_header']; ?></h2>
</div>

<form name="cleanupForm" action="<?= $router->pathFor('cleanup'); ?>" method="post">
    <div class="card__body scrollable">
        <p>
            <?= $error ? $language['cleanup_error'] : $language['cleanup_disclaimer']; ?>;
        </p>

        <table>
            <thead>
                <tr>
                    <th><?= $language['cleanup_dir_table_header']; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cleanupList as $cleanupEntry): ?>
                    <tr>
                        <td <?= $error ? 'class="error"' : ''; ?>>
                            <?= $cleanupEntry; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- http://support.microsoft.com/kb/2977636 -->
    <input type="hidden" name="ie11-dummy-payload" value="some-payload"/>

    <div class="card__footer flex-container">
        <div class="flex-item flex-container cleanup-file-counter">
            <div class="clearCacheSpinner flex-item">
                <i class="loading-indicator"></i>
            </div>

            <div class="fileCounterContainer flex-item">
                <span class="counter">0</span>
                <span class="description"><?= sprintf($language['deleted_files'], count($cleanupList)); ?></span>
            </div>
        </div>

        <button type="button"
               class="btn btn-primary flex-item flex-right startCleanUpProcess"
               data-clearCacheUrl="<?= $router->pathFor('clearCache'); ?>">
            <?= $language['forward']; ?>
        </button>
    </div>

    <div class="error-message-container alert alert-error">
        <p><?= $language['cache_clear_error']; ?></p>
    </div>
</form>

<?php echo $renderer->fetch('_footer.php'); ?>
