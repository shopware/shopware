<?php
use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration444 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== AbstractMigration::MODUS_INSTALL) {
            return;
        }

        $this->addImprintToBottomGroup();
    }

    private function addImprintToBottomGroup()
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_cms_static` WHERE `description` LIKE 'Impressum' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
UPDATE `s_cms_static` SET `grouping` = 'gLeft|gBottom2' WHERE `id` = @parent;
EOD;
        $this->addSql($sql);
    }
}