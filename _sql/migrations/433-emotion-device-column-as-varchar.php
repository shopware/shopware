<?php
class Migrations_Migration433 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_emotion` CHANGE `device` `device` VARCHAR(255) DEFAULT '0,1,2,3,4';
EOD;
        $this->addSql($sql);
    }
}