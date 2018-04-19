<?php
class Migrations_Migration414 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addProductBoxLayoutColumn();
        $this->addSearchProductBoxLayoutSwitch();
    }

    private function addProductBoxLayoutColumn()
    {
        $sql = <<<EOT
ALTER TABLE s_categories
ADD product_box_layout varchar(50) NULL DEFAULT NULL
EOT;
        $this->addSql($sql);
    }

    private function addSearchProductBoxLayoutSwitch()
    {
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(190, 'searchProductBoxLayout', 's:5:"basic"', 'Produkt Layout', 'Mit Hilfe des Produkt Layouts können Sie entscheiden, wie Ihre Produkte auf der Suchergebnis-Seite dargestellt werden sollen. Wählen Sie eines der drei unterschiedlichen Layouts um die Ansicht perfekt auf Ihr Produktsortiment abzustimmen.', 'product-box-layout-select', 0, 0, 1, NULL, NULL, NULL);

SET @elementId = (SELECT `id` FROM `s_core_config_elements` WHERE `form_id`= 190 AND `name`="searchProductBoxLayout" LIMIT 1);

INSERT IGNORE INTO `s_core_config_element_translations` (`label`, `description`, `locale_id`, `element_id`)
VALUES ('Product layout', 'Product layout allows you to control how your products are presented on the search result page. Choose between three different layouts to fine-tune your product display.', 2, @elementId);
EOD;
        $this->addSql($sql);
    }
}
