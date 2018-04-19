<?php
class Migrations_Migration706 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOL
DROP TABLE IF EXISTS `s_user_debit`;
EOL;
        $this->addSql($sql);
    }
}
