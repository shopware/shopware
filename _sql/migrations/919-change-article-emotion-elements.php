<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration919 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $this->addVariantSelectionProductSlider();
        $this->addVariantSelectionProductElement();
        $this->changeSelectedProductsOfProductSlider();
        $this->convertSelectedProductsOfProductSlider();
    }

    /**
     * add variant selection for product element
     */
    private function addVariantSelectionProductElement()
    {
        $sql = <<<SQL
SET @articleComponentId = (SELECT id FROM s_library_component WHERE x_type = 'emotion-components-article');
INSERT INTO s_library_component_field (componentID, name, x_type, value_type, field_label, support_text, help_title, help_text, store, display_field, value_field, default_value, allow_blank, translatable, position)
VALUES (@articleComponentId, 'variant', 'emotion-components-fields-variant', '', '', '', '', '', '', '', '', '', 0, 0, 9);
SQL;
        $this->addSql($sql);
    }

    /**
     * add variant selection for product slider
     */
    private function addVariantSelectionProductSlider()
    {
        $sql = <<<SQL
SET @articleSliderComponentId = (SELECT id FROM s_library_component WHERE x_type = 'emotion-components-article-slider');
INSERT INTO s_library_component_field (componentID, name, x_type, value_type, field_label, support_text, help_title, help_text, store, display_field, value_field, default_value, allow_blank, translatable, position)
VALUES (@articleSliderComponentId, 'selected_variants', 'hidden', '', '', '', '', '', '', '', '', '', 0, 0, 100);
SQL;
        $this->addSql($sql);
    }

    /**
     * change value type and position of selected products of product slider
     */
    private function changeSelectedProductsOfProductSlider()
    {
        $sql = <<<SQL
UPDATE `s_library_component_field` AS comp_field
SET comp_field.`value_type` = '', comp_field.position = 100
WHERE comp_field.`name` = 'selected_articles'
SQL;
        $this->addSql($sql);
    }

    /**
     * convert product slider values
     */
    private function convertSelectedProductsOfProductSlider()
    {
        $emotionElementQuery = <<<SQL
SELECT emoEle.`id`, `value`
FROM `s_emotion_element_value` AS emoEle
INNER JOIN s_library_component_field AS libComp
    ON emoEle.fieldID = libComp.`id`
    AND libComp.`name` = 'selected_articles';
SQL;

        $emotionElements = $this->getConnection()->query($emotionElementQuery)->fetchAll(\PDO::FETCH_KEY_PAIR);

        $updateEmotionElementQuery = <<<SQL
UPDATE s_emotion_element_value
SET `value` = :newValue
WHERE `id` = :id;
SQL;
        $updateEmotionElementStatement = $this->getConnection()->prepare($updateEmotionElementQuery);

        foreach ($emotionElements as $id => $emotionElement) {
            $products = json_decode($emotionElement, true);
            $orderNumbers = array_column($products, 'ordernumber');
            $newValue = '|' . implode('|', $orderNumbers) . '|';

            $updateEmotionElementStatement->execute([
                'id' => $id,
                'newValue' => $newValue
            ]);
        }
    }
}
