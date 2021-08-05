<?php declare(strict_types=1);
echo $app->getContainer()->get('renderer')->fetch('_header.php'); ?>

<div class="card__title">
    <h2><?= $t->t('database-configuration_header'); ?></h2>
</div>


<form
    action="<?= $menuHelper->getCurrentUrl(); ?>"
    method="post"
    data-ajaxDatabaseSelection="true"
    data-url="<?= $app->getContainer()->get('router')->pathFor('database'); ?>">

    <div class="card__body">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= $error; ?>
        </div>
    <?php endif; ?>

    <p>
        <?= $t->t('database-configuration_info'); ?>
    </p>

    <p>
        <label for="c_database_host"><?= $t->t('database-configuration_field_host'); ?></label>
        <input type="text" value="<?= isset($parameters['c_database_host']) ? $parameters['c_database_host'] : 'localhost'; ?>" name="c_database_host" id="c_database_host" required="required" />
    </p>

    <div class="form-group form-group--50">
        <p>
            <label for="c_database_user"><?= $t->t('database-configuration_field_user'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_user']) ? $parameters['c_database_user'] : ''; ?>" name="c_database_user" id="c_database_user" required="required" />
        </p>

        <p>
            <label for="c_database_password"><?= $t->t('database-configuration_field_password'); ?></label>
            <input type="password" value="<?= isset($parameters['c_database_password']) ? $parameters['c_database_password'] : ''; ?>" name="c_database_password" id="c_database_password" />
        </p>
    </div>

    <div class="custom-switch">
        <input type="checkbox" name="advanced-settings" value="1" id="c_advanced" class="toggle" data-href="#advanced-settings" <?= isset($_POST['advanced-settings']) ? 'checked' : ''; ?> />
        <label for="c_advanced" class="toggle">
            <?= $t->t('database-configuration_advanced_settings'); ?>
        </label>
    </div>

    <div class="<?= isset($_POST['advanced-settings']) ? '' : 'is--hidden'; ?>" id="advanced-settings">
        <p>
            <label for="c_database_port"><?= $t->t('database-configuration_field_port'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_port']) ? $parameters['c_database_port'] : '3306'; ?>" name="c_database_port" id="c_database_port" required="required" />
        </p>

        <p>
            <label for="c_database_socket"><?= $t->t('database-configuration_field_socket'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_socket']) ? $parameters['c_database_socket'] : ''; ?>" name="c_database_socket" id="c_database_socket" />
        </p>

        <p>
            <label for="c_database_ssl_ca_path"><?= $t->t('database-configuration_field_ssl_ca_path'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_ssl_ca_path']) ? $parameters['c_database_ssl_ca_path'] : ''; ?>" name="c_database_ssl_ca_path" id="c_database_ssl_ca_path" />
        </p>

        <p>
            <label for="c_database_ssl_cert_path"><?= $t->t('database-configuration_field_ssl_cert_path'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_ssl_cert_path']) ? $parameters['c_database_ssl_cert_path'] : ''; ?>" name="c_database_ssl_cert_path" id="c_database_ssl_cert_path" />
        </p>

        <p>
            <label for="c_database_ssl_cert_key_path"><?= $t->t('database-configuration_field_ssl_cert_key_path'); ?></label>
            <input type="text" value="<?= isset($parameters['c_database_ssl_cert_key_path']) ? $parameters['c_database_ssl_cert_key_path'] : ''; ?>" name="c_database_ssl_cert_key_path" id="c_database_ssl_cert_key_path" />
        </p>


        <div class="custom-checkbox">
            <input type="checkbox"
                   id="c_database_ssl_dont_verify_cert"
                   name="c_database_ssl_dont_verify_cert"
                    <?= isset($parameters['c_database_ssl_dont_verify_cert']) ? 'checked' : ''; ?>
                   value="1"/>
            <label for="c_database_ssl_dont_verify_cert"><?= $t->t('database-configuration_field_ssl_dont_verify_cert'); ?></label>
        </div>
    </div>

    <p>
        <label for="c_database_schema"><?= $t->t('database-configuration_field_database'); ?></label>
        <input
            data-ajaxDatabaseSelection="true"
            data-url="<?= $app->getContainer()->get('router')->pathFor('database'); ?>"
            type="text"
            value="<?= isset($parameters['c_database_schema']) ? $parameters['c_database_schema'] : ''; ?>"
            name="c_database_schema"
            id="c_database_schema"
            required="required" />
    </p>
    <div id="non-empty-db-warning" class="alert alert-warning is--hidden">
        <span class="icon-warning"></span>
        <?= $t->t('database-configuration_non_empty_database'); ?>
    </div>
    <p>
        <div class="custom-checkbox c_create_database">
            <input id="c_database_create_schema_new" type="checkbox" />
            <label for="c_database_create_schema_new"><?= $t->t('database-configuration_field_new_database'); ?></label>
        </div>

        <input type="text" name="c_database_schema" id="c_database_schema_new" value="<?= isset($parameters['c_database_schema']) ? $parameters['c_database_schema'] : ''; ?>" />
    </p>

    </div>

    <div class="card__footer flex-container">
        <a href="<?= $menuHelper->getPreviousUrl(); ?>" class="btn btn-default flex-item"><?= $t->t('back'); ?></a>
        <button type="submit" class="btn btn-primary flex-item flex-right"><?= $t->t('start_installation'); ?></button>
    </div>
</form>


<?php echo $app->getContainer()->get('renderer')->fetch('_footer.php'); ?>
