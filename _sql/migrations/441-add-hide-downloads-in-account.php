<?php
class Migrations_Migration441 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_core_config_forms` WHERE `name`='Esd');
INSERT IGNORE INTO `s_core_config_elements`
(`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES (NULL, @parent, 'showEsd', 'b:1;', 'Sofortdownloads im Account anzeigen', 'Sofortdownloads können weiterhin über die Bestellübersicht heruntergeladen werden.', 'boolean', '1', '5', '1', NULL, NULL, NULL);

SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'showEsd' LIMIT 1);
SET @localeID = (SELECT id FROM s_core_locales WHERE locale='en_GB');
INSERT IGNORE INTO s_core_config_element_translations
(element_id, locale_id, label, description)
VALUES (@elementID, @localeID, 'Show instant downloads in account', 'Instant downloads can already be downloaded from the order details page.');
EOD;

        $this->addSql($sql);
    }
}
