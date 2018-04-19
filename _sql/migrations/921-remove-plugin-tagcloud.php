<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

class Migrations_Migration921 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     */
    public function up($modus)
    {
        $this->fetchPluginId();
        $this->deleteConfigElements();
        $this->deleteListeners();
        $this->deletePlugin();
    }

    private function fetchPluginId()
    {
        $sql = <<<'SQL'
SET @pluginId = (
  SELECT id
  FROM s_core_plugins
  WHERE name = "TagCloud"
  LIMIT 1
);
SQL;
        $this->addSql($sql);
    }

    private function deleteConfigElements()
    {
        $sql = <<<'SQL'
DELETE form, element, translation, value
FROM s_core_config_forms form
LEFT JOIN s_core_config_elements element ON element.form_id = form.id
LEFT JOIN s_core_config_element_translations translation ON translation.element_id = element.id
LEFT JOIN s_core_config_values value ON value.element_id = element.id
WHERE form.plugin_id = @pluginId
SQL;
        $this->addSql($sql);
    }

    private function deleteListeners()
    {
        $this->addSql('DELETE FROM s_core_subscribes WHERE pluginID = @pluginId');
    }

    private function deletePlugin()
    {
        $this->addSql('DELETE FROM s_core_plugins WHERE id = @pluginId');
    }
}
