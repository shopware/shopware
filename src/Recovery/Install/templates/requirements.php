<?php declare(strict_types=1);
echo $app->getContainer()->get('renderer')->fetch('_header.php'); ?>

<div class="card__title">
    <h2><?= $t->t('requirements_header'); ?></h2>
</div>

<div class="card__body scrollable">
    <?php if ($error) : ?>
        <div class="alert-hero error">
            <div class="alert-hero-icon">
                <i class="icon-warning"></i>
            </div>
            <h3 class="alert-hero-title"><?= $t->t('requirements_error_title'); ?></h3>
            <div class="alert-hero-text"><?= $t->t('requirements_error'); ?></div>
        </div>
    <?php else : ?>
        <div class="alert-hero success">
            <div class="alert-hero-icon">
                <i class="icon-checkmark"></i>
            </div>
            <h3 class="alert-hero-title"><?= $t->t('requirements_success_title'); ?></h3>
            <div class="alert-hero-text"><?= $t->t('requirements_success'); ?></div>
        </div>
    <?php endif; ?>

    <h4 class="requirement-group <?php if (!$pathError): ?>success<?php endif; ?><?php if ($pathError): ?>error open<?php endif; ?>"
        data-toggle="collapse"
        data-target="#permissions">
        <span class="requirement-group-title"><?= $t->t('requirements_header_files'); ?> <span class="status-indicator"></span></span>
        <i class="icon-chevron-down"></i>
    </h4>

    <div id="permissions" class="<?php if (!$pathError): ?>is--hidden<?php endif; ?>">
        <p class="requirement-info-text">
            <?= $t->t('requirements_files_info'); ?>
        </p>

        <table>
            <tbody>
                <tr>
                    <th><?= $t->t('requirements_table_files_col_check'); ?></th>
                    <th><?= $t->t('requirements_table_files_col_status'); ?></th>
                </tr>
                <?php foreach ($systemCheckResultsWritePermissions as $systemCheckResult): ?>
                    <tr>
                        <td><?= $systemCheckResult['name']; ?></td>
                        <td>
                            <span class="status-indicator <?= $systemCheckResult['existsAndWriteable'] ? 'success' : 'error'; ?>"></span>
                            <?= $systemCheckResult['existsAndWriteable'] ? $t->t('requirements_status_ready') : $t->t('requirements_status_error'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4 class="requirement-group <?php if (!$systemError): ?>success<?php endif; ?><?php if ($systemError): ?>error open<?php endif; ?>"
        data-toggle="collapse"
        data-target="#systemchecks">
        <span class="requirement-group-title"><?= $t->t('requirements_header_system'); ?> <span class="status-indicator"></span></span>
        <i class="icon-chevron-down"></i>
    </h4>

    <div id="systemchecks" class="<?php if (!$systemError): ?>is--hidden<?php endif; ?>">
        <p class="requirement-info-text">
            <?= $t->t('requirements_php_info'); ?>
        </p>

        <table>
            <thead>
                <tr>
                    <th><?= $t->t('requirements_system_col_check'); ?></th>
                    <th><?= $t->t('requirements_system_col_status'); ?></th>
                    <th><?= $t->t('requirements_system_col_required'); ?></th>
                    <th><?= $t->t('requirements_system_col_found'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($systemCheckResults as $systemCheckResult): ?>
                    <?php
                        if ($systemCheckResult['status'] === 'ok') {
                            $class = 'success';
                        } else {
                            if ($systemCheckResult['status'] === 'error') {
                                $class = 'error';
                            } else {
                                $class = 'warning';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= $systemCheckResult['name']; ?></td>
                        <td><span class="status-indicator <?= $class; ?>"></span>
                            <?php
                                if ($systemCheckResult['status'] === 'ok') {
                                    echo $t->t('requirements_status_ready');
                                } else {
                                    if ($systemCheckResult['status'] === 'error') {
                                        echo $t->t('requirements_status_error');
                                    } else {
                                        echo $t->t('requirements_status_warning');
                                    }
                                }
                            ?>
                        </td>
                        <td><?= $systemCheckResult['required']; ?></td>
                        <td><?= empty($systemCheckResult['version']) ? '0' : $systemCheckResult['version']; ?></td>
                    </tr>
                    <?php if (!empty($systemCheckResult['notice'])) : ?>
                        <tr class="notice-text">
                            <td colspan="4">
                                <p><i class="icon-info22"></i> <?= $systemCheckResult['notice']; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card__footer">
    <form action="<?= $menuHelper->getCurrentUrl(); ?>" method="post" class="flex-container">
        <a href="<?= $menuHelper->getPreviousUrl(); ?>" class="btn btn-default flex-item"><?= $t->t('back'); ?></a>
        <button type="submit" class="btn btn-primary flex-item flex-right" <?php if ($error): ?>disabled="disabled"<?php endif; ?>>
            <?= $t->t('forward'); ?>
        </button>
    </form>
</div>

<?php echo $app->getContainer()->get('renderer')->fetch('_footer.php'); ?>
