<?php

class Migrations_Migration637 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
SET @elementId = (
  SELECT s_core_config_elements.id
  FROM s_core_config_forms
  INNER JOIN s_core_config_elements ON s_core_config_elements.form_id = s_core_config_forms.id
  WHERE s_core_config_forms.name = 'Frontend77' AND s_core_config_elements.name = 'similarlimit'
LIMIT 1
);

UPDATE `s_core_config_elements` SET `description` = 'Wenn keine ähnlichen Produkte gefunden wurden, kann Shopware automatisch alternative Vorschläge generieren. Sie können die automatischen Vorschläge aktivieren, indem Sie einen Wert größer als 0 eintragen. Das Aktivieren kann sich negativ auf die Performance des Shops auswirken.' WHERE `id` = @elementId;
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
UPDATE `s_core_config_element_translations` SET `description` = 'If no similar articles are found, Shopware can automatically generates alternative suggestions. You can activate these suggestions if you enter a number greater than 0. May decrease performance when loading these articles.' WHERE `element_id` = @elementId AND `locale_id` = 2;
SQL;
        $this->addSql($sql);

        if ($modus == \Shopware\Framework\Migration\AbstractMigration::MODUS_UPDATE) {
            return;
        }

        $sql = <<<SQL
UPDATE `s_core_config_elements` SET `value` = 's:1:"0";' WHERE `id` = @elementId;
SQL;
        $this->addSql($sql);

    }
}
