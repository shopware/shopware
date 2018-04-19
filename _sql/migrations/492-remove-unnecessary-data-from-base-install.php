<?php
class Migrations_Migration492 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== self::MODUS_INSTALL) {
            return;
        }
        $this->addSql('DELETE FROM `s_core_shops` WHERE id != 1;');
        $this->addSql('DELETE FROM `s_core_currencies` WHERE id != 1;');
        $this->addSql('DELETE FROM `s_core_shop_currencies` WHERE `shop_id` = 1 AND `currency_id` = 2;');
        $this->addSql('DELETE FROM `s_categories` WHERE id NOT IN (1, 3);');
    }
}
