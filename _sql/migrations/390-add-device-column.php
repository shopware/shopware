<?php
class Migrations_Migration390 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
       ALTER TABLE `s_emotion` ADD `device` INT( 1 ) NOT NULL;
EOD;
        $this->addSql($sql);
    }
}