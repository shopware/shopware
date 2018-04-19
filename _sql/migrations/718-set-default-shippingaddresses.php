<?php

class Migrations_Migration718 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        if ($modus == self::MODUS_INSTALL) {
            return;
        }

        $sql = <<<SQL
SET foreign_key_checks=0;

UPDATE s_user
SET default_shipping_address_id = default_billing_address_id;

SET foreign_key_checks=1;
SQL;

        $this->addSql($sql);
    }
}
