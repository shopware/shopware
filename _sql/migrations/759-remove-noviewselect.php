<?php

class Migrations_Migration759 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE s_categories DROP noviewselect");
    }
}
