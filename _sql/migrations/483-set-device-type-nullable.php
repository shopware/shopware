<?php
class Migrations_Migration483 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
          ALTER TABLE `s_order` CHANGE `deviceType` `deviceType` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;
EOD;
        $this->addSql($sql);
    }
}