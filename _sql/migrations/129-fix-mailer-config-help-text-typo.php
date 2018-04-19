<?php
class Migrations_Migration129 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_config_elements` SET `description` = 'mail, smtp oder file' WHERE `description` = 'mail, SMTP oder file';
EOD;
        $this->addSql($sql);
    }
}
