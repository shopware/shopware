<?php
class Migrations_Migration403 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE
                `s_core_config_element_translations` `translations`,
                `s_core_config_elements` `elements`,
                `s_core_config_forms` `forms`,
                `s_core_locales` `locales`
            SET
                `translations`.`description`= '(Hex-Code, only applies to the Emotion template)',
                `elements`.`description`= '(Hex-Code, betrifft nur das Emotion-Template)'
            WHERE
                `translations`.`element_id` = `elements`.`id`
            AND `elements`.`label` LIKE 'Warenkorb%farbe'
            AND `elements`.`form_id` = `forms`.`id`
            AND `forms`.`label` = 'Bestellabschluss'
            AND `translations`.`locale_id` = `locales`.`id`
            AND `locales`.`locale` = 'en_GB'
EOD;

        $this->addSql($sql);
    }
}
