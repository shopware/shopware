<?php

class Migrations_Migration717 extends Shopware\Framework\Migration\AbstractMigration
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
INNER JOIN s_user_addresses ON s_user.id = s_user_addresses.user_id
SET default_billing_address_id = s_user_addresses.id;

SET foreign_key_checks=1;
SQL;

        $this->addSql($sql);
    }
}
