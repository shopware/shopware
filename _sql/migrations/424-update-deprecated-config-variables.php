<?php
class Migrations_Migration424 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "UPDATE s_core_config_elements SET description = 'Betrifft nur das Emotion-Template'
            WHERE s_core_config_elements.name LIKE 'paymentEditingInCheckoutPage' AND description IS NULL";
        $this->addSql($sql);

        $sql = "UPDATE s_core_config_elements SET description = 'Betrifft nur das Emotion-Template'
            WHERE s_core_config_elements.name LIKE 'showbundlemainarticle' AND description IS NULL";
        $this->addSql($sql);

        $sql = <<<SQL
            UPDATE
                `s_core_config_element_translations` `translations`,
                `s_core_config_elements` `elements`,
                `s_core_config_forms` `forms`,
                `s_core_locales` `locales`
            SET
                `translations`.`description`= 'Only applies to the Emotion template'
            WHERE
                `translations`.`element_id` = `elements`.`id`
            AND `elements`.`name` IN ('showbundlemainarticle', 'paymentEditingInCheckoutPage')
            AND `elements`.`form_id` = `forms`.`id`
            AND `translations`.`locale_id` = `locales`.`id`
            AND `locales`.`locale` = 'en_GB'
SQL;
        $this->addSql($sql);
    }
}
