<?php
class Migrations_Migration220 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // The intermediate table t is needed to avoid a MySql error
        // see http://stackoverflow.com/questions/5816840/delete-i-cant-specify-target-table

        $sql = <<<'EOD'
        DELETE FROM s_core_payment_data WHERE id IN ( SELECT * FROM (
        SELECT s_core_payment_data.payment_mean_id
                   FROM s_core_payment_data
                  GROUP
                     BY s_core_payment_data.payment_mean_id
                      , s_core_payment_data.user_id
                 HAVING COUNT(1) > 1
        ) as t);

        ALTER IGNORE TABLE `s_core_payment_data` ADD UNIQUE (
            `payment_mean_id` ,
            `user_id`
        );
EOD;
        $this->addSql($sql);
    }
}
