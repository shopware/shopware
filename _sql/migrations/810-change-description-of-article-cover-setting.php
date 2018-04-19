<?php

class Migrations_Migration810 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql(<<<SQL
SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'forceArticleMainImageInListing');

UPDATE `s_core_config_elements`
  SET `label` = 'Immer das Artikel-Vorschaubild anzeigen', `description` = 'z.B. im Listing oder beim Auswahl- und Bildkonfigurator ohne ausgewählte Variante'
  WHERE `id` = @elementId;

UPDATE `s_core_config_element_translations`
  SET `label` = 'Always display the article preview image', `description` = 'e.g. in listings or when using selection or picture configurator with no selected variant'
  WHERE `element_id` = @elementId;
SQL
        );
    }
}
