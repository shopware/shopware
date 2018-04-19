<?php
use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration425 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== AbstractMigration::MODUS_INSTALL) {
            return;
        }

        $this->deleteOldTemplates();
    }

    private function deleteOldTemplates()
    {
        $sql = <<<SQL
DELETE FROM s_core_templates WHERE `version` = 1 OR `version` = 2;
SQL;

        $this->addSql($sql);
    }
}
