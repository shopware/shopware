<?php

class Migrations_Migration600 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('SET @element_id = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = "similarlimit");');

        $this->updateDescription();
        $this->updateTranslatedDescription();
    }

    private function updateDescription()
    {
        $sql = <<<EOD
                UPDATE `s_core_config_elements`
                SET
                `description` = "Wenn keine ähnlichen Produkte gefunden wurden, kann Shopware automatisch alternative Vorschläge generieren. Sie können die automatischen Vorschläge deaktivieren indem Sie 0 eintragen. Das deaktivieren kann sich positiv auf die Performance dieser geladenen Artikel auswirken."
                WHERE `name` = "similarlimit";
EOD;
        $this->addSql($sql);
    }

    private function updateTranslatedDescription()
    {
        $sql = <<<EOD
                UPDATE `s_core_config_element_translations`
                SET
                `description` = "If no similar articles are found, Shopware can automatically generates alternative suggestions. You can disable these suggestions if you enter 0. May increase performance when loading these articles."
                WHERE `element_id` = @element_id;
EOD;
        $this->addSql($sql);
    }
}