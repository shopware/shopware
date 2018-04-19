<?php

class Migrations_Migration777 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('UPDATE s_user SET birthday = NULL WHERE birthday = "0000-00-00"');
    }
}
