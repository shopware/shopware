<?php

class Migrations_Migration746 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
INSERT IGNORE INTO s_user_shippingaddress
	(`userID`, `company`, `department`, `salutation`, `firstname`, `lastname`, `street`, `zipcode`, `city`, `countryID`, `stateID`, `additional_address_line1`, `additional_address_line2`, `title`)
SELECT
	 addresses.`user_id` as userID, addresses.`company`, addresses.`department`, addresses.`salutation`, addresses.`firstname`, addresses.`lastname`, addresses.`street`, addresses.`zipcode`, addresses.`city`, addresses.`country_id` as countryID, addresses.`state_id` as stateID, addresses.`additional_address_line1`, addresses.`additional_address_line2`, addresses.`title`
FROM 
    s_user_addresses addresses
INNER JOIN s_user user ON user.default_shipping_address_id = addresses.id
SQL;

        $this->addSql($sql);
    }
}
