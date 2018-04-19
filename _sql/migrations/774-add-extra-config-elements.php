<?php

class Migrations_Migration774 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_UPDATE) {
            return;
        }
        $today = new \DateTime();
        $installationDate = serialize($today->format('Y-m-d H:i'));


        $sql = <<<SQL
    INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`)
    VALUES 
    (NULL, 0, 'installationDate', '$installationDate', 'Installationsdatum', NULL, 'text', 0, 0, 0, NULL),
    (NULL, 0, 'installationSurvey', 'b:1;', 'Umfrage zur Installation', NULL, 'boolean', 0, 0, 0, NULL)
SQL;
        $this->addSql($sql);
    }
}
