<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration909 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $this->addSql("SET @formId = (select id from s_core_config_forms WHERE name = 'Frontend30' LIMIT 1);");

        $sql = <<<EOD
    INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`) VALUES
    (NULL, @formId, 'displayListingBuyButton', 'b:0;', 'Kaufenbutton im Listing anzeigen', '', 'checkbox', 1, 0, 1);
EOD;
        $this->addSql($sql);

        $sql = <<<EOD
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formId AND `name`='displayListingBuyButton');
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES (@elementID, 2, 'Display buy button in listing', '');
EOD;

        $this->addSql($sql);
    }
}
