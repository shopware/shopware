<?php

class Migrations_Migration479 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_INSTALL) {
            $sql = "INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
                    VALUES (NULL, '0', 'updateWizardStarted', 'b:1;', '', '', 'checkbox', '0', '0', '1', NULL, NULL);";

            $this->addSql($sql);
        }
    }

}