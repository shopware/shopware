<?php

class Migrations_Migration716 extends Shopware\Framework\Migration\AbstractMigration
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

INSERT INTO s_user_addresses (original_type, original_id, migration_id, user_id, company, department, salutation, firstname, lastname, street, zipcode, city, additional_address_line1, additional_address_line2, country_id, state_id, phone, ustid)
(
  SELECT original_type, original_id, id, user_id, company, department, salutation, firstname, lastname, street, zipcode, city, additional_address_line1, additional_address_line2, country_id, state_id, phone, ustid
  FROM s_user_addresses_migration
);

SET foreign_key_checks=1;
SQL;

        $this->addSql($sql);
    }
}
