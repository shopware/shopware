<?php
class Migrations_Migration435 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_library_component_field` SET `support_text` = 'Bitte beachten Sie, dass diese Einstellung nur Auswirkungen auf das "Emotion"-Template hat.' WHERE `name` = 'banner_slider_numbers';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
UPDATE `s_library_component_field` SET `support_text` = 'Bitte beachten Sie, dass diese Einstellung nur Auswirkungen auf das "Emotion"-Template hat.' WHERE `name` = 'manufacturer_slider_numbers';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
UPDATE `s_library_component_field` SET `support_text` = 'Bitte beachten Sie, dass diese Einstellung nur Auswirkungen auf das "Emotion"-Template hat.' WHERE `name` = 'article_slider_numbers';
EOD;
        $this->addSql($sql);
    }
}