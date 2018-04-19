<?php

class Migrations_Migration501 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            ALTER TABLE `s_campaigns_mailaddresses`
            ADD `added` DATETIME DEFAULT NULL;
EOD;
        $this->addSql($sql);

        $sql = "UPDATE s_campaigns_mailaddresses ca
                SET added = (SELECT cm.added FROM s_campaigns_maildata cm WHERE cm.email = ca.email LIMIT 1)";
        $this->addSql($sql);
    }
}