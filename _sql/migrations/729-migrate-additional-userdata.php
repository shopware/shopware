<?php

class Migrations_Migration729 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // migrate existing data to s_user table
        $sql = <<<SQL
        UPDATE s_user AS u
        JOIN s_user_billingaddress as a ON a.userID = u.id
        SET
          u.salutation = a.salutation,
          u.firstname = a.firstname,
          u.lastname = a.lastname,
          u.birthday = a.birthday;
SQL;
        $this->addSql($sql);
    }
}
