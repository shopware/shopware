<?php

class Migrations_Migration750 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Create 'purchaseprice' field in 's_articles_details'
        $sql = <<<SQL
ALTER TABLE `s_articles_details`
    ADD `purchaseprice` double NOT NULL DEFAULT '0';
SQL;
        $this->addSql($sql);

        // Create 'purchaseprice' field in 's_article_configurator_template'
        $sql = <<<SQL
ALTER TABLE `s_article_configurator_templates`
    ADD `purchaseprice` double NOT NULL DEFAULT '0';
SQL;
        $this->addSql($sql);

        // Migrate existing 'baseprice' to 'purchaseprice' using the prices of the default shop's customer group
        // and having a 'from' value of 1
        $sql = <<<SQL
UPDATE `s_articles_details` AS ad
JOIN `s_articles_prices` AS ap
    ON ap.`articledetailsID` = ad.`id`
SET
    ad.`purchaseprice` = IFNULL(ap.`baseprice`, 0)
WHERE ap.`from` = 1
AND ap.`pricegroup` = (
    SELECT cg.`groupkey`
    FROM `s_core_customergroups` AS cg
    JOIN `s_core_shops` AS s
        ON s.`customer_group_id` = cg.`id`
    WHERE s.`default` = 1
);
SQL;
        $this->addSql($sql);

        // Remove the old 'baseprice' field from 's_article_configurator_template_prices'
        $sql = <<<SQL
ALTER TABLE `s_article_configurator_template_prices`
    DROP `baseprice`;
SQL;
        $this->addSql($sql);
    }
}
