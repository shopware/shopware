<?php
class Migrations_Migration391 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_UPDATE) {
            return;
        }

        $this->addSql("ALTER TABLE s_articles_attributes MODIFY attr17 varchar(255)");
    }
}
