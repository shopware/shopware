<?php

class Migrations_Migration503 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateMultiEditName();

        $sql = "SELECT * FROM `s_core_config_forms` WHERE `name` = 'MultiEdit';";
        $configForm = $this->connection->query($sql)->fetch();

        if ($modus === self::MODUS_UPDATE && !$configForm) {
            $this->addSql("SET @localeID = (SELECT `id` FROM `s_core_locales` WHERE `locale` = 'en_GB' LIMIT 1);");

            $this->insertConfigForm();
            $this->insertConfigElements();
            $this->insertConfigElementTranslations();
        }
    }

    /**
     * updates the plugin name
     */
    private function updateMultiEditName()
    {
        $this->addSql("UPDATE `s_core_config_forms` SET `name` = 'MultiEdit' WHERE `name` = 'SwagMultiEdit';");
    }

    /**
     * inserts the config elements
     */
    private function insertConfigElements()
    {
        $sql = <<<EOD
                INSERT IGNORE INTO `s_core_config_elements`
                (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
                VALUES
                (@formID, 'addToQueuePerRequest', 'i:2048;', 'Anzahl der Produkte pro Queue-Request', 'Anzahl der Produkte, die je Request in den Queue geladen werden. Je größer die Zahl, desto länger dauern die Requests. Zu kleine Werte erhöhen den Overhead.', 'number', 1, 0, 0, NULL, NULL, 'a:1:{s:10:"attributes";a:1:{s:8:"minValue";i:100;}}'),
                (@formID, 'batchItemsPerRequest', 'i:2048;', 'Anzahl der Produkte pro Batch-Request', 'Anzahl der Produkte, die je Request in den Queue geladen werden. Je größer die Zahl, desto länger dauern die Requests. Zu kleine Werte erhöhen den Overhead.', 'number', 1, 0, 0, NULL, NULL, 'a:1:{s:10:"attributes";a:1:{s:8:"minValue";i:50;}}'),
                (@formID, 'enableBackup', 'b:1;', 'Rückgängig-Funktion aktivieren', 'Ermöglicht es, einzelne Mehrfach-Änderungen rückgängig zu machen. Diese Funktion ersetzt kein Backup.', 'checkbox', 0, 0, 0, NULL, NULL, 'a:0:{}'),
                (@formID, 'clearCache', 'b:0;', 'Automatische Cache-Invalidierung aktivieren:', 'Invalidiert den Cache für jedes Produkt, das geändert wird. Bei vielen Produkten kann sich das negativ auf die Dauer des Vorgangs auswirken. Es wird daher empfohlen, den Cache nach Ende des Vorgangs manuell zu leeren.', 'checkbox', 0, 0, 0, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);
    }

    /**
     * inserts the config element translations
     */
    private function insertConfigElementTranslations()
    {
        $this->addSql("SET @elementQueueRequestId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'addToQueuePerRequest' LIMIT 1);");
        $this->addSql("SET @elementBatchItemsId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'batchItemsPerRequest' LIMIT 1);");
        $this->addSql("SET @elementBackupId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'enableBackup' LIMIT 1);");
        $this->addSql("SET @elementCacheId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'clearCache' LIMIT 1);");

        $sql = <<<EOD
                INSERT IGNORE INTO `s_core_config_element_translations`
                (`element_id`, `locale_id`, `label`, `description`)
                VALUES
                (@elementCacheId, @localeID, 'Invalidate products in batch mode', 'Will clear the cache for any product, which was changed in batch mode. When changing many products, this will be quite slow. Its recommended to clear the cache manually afterwards.'),
                (@elementBackupId, @localeID, 'Enable restore feature', 'Enable the restore feature.'),
                (@elementQueueRequestId, @localeID, 'Number of products per queue request', 'The number of products, you want to add to queue per request. The higher the value, the longer a request will take. Too low values will result in overhead.'),
                (@elementBatchItemsId, @localeID, 'Products per batch request', 'The number of products, you want to be processed per request. The higher the value, the longer a request will take. Too low values will result in overhead');
EOD;
        $this->addSql($sql);
    }

    /**
     * insert the config form and config form translation
     */
    private function insertConfigForm()
    {
        $this->addSql("SET @parentFormId = (SELECT `id` FROM `s_core_config_forms` WHERE name = 'Other' and parent_id IS NULL LIMIT 1);");

        $sql = <<<EOD
                INSERT IGNORE INTO `s_core_config_forms`
                (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`)
                VALUES
                (@parentFormId, 'MultiEdit', 'Mehrfachänderung', NULL, 0, 0, NULL);
EOD;
        $this->addSql($sql);

        $this->addSql("SET @formID = (SELECT `id` FROM `s_core_config_forms` WHERE name = 'MultiEdit' LIMIT 1);");

        $sql = <<<EOD
                INSERT IGNORE INTO `s_core_config_form_translations`
                (`locale_id`, `form_id`, `label`)
                VALUES
                (@localeID, @formID, 'Multi edit');
EOD;
        $this->addSql($sql);
    }
}