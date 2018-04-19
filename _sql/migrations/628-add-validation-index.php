<?php
class Migrations_Migration628 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOL
ALTER TABLE `s_user` ADD INDEX ( `validation` );
EOL;
        $this->addSql($sql);
    }
}
