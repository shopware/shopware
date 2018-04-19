<?php

class Migrations_Migration616 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateProductStreamEmotionField();
    }

    private function updateProductStreamEmotionField()
    {
        $sql = <<<'EOD'
UPDATE `s_library_component_field`
SET `x_type` = 'productstreamselection', `display_field` = 'name', `value_field` = 'id'
WHERE `name` = 'article_slider_stream';
EOD;
        $this->addSql($sql);
    }
}