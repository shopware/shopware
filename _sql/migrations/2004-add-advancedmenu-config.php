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

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration2004 extends AbstractMigration
{
    /**
     * {@inheritdoc}
     */
    public function up($modus)
    {
        $this->addAdvancedMenuToCache();
        if (!empty($this->getPluginInstalledStatus())) {
            $this->updateAdvancedMenuForm();
        } else {
            $this->deletePluginEntrys();
            $this->createAdvancedMenuForm();
            $this->createAdvancedMenuFormElements();
        }
    }

    private function getPluginInstalledStatus()
    {
        return $this->connection->query("SELECT active FROM s_core_plugins WHERE name = 'AdvancedMenu'")->fetchColumn();
    }

    private function deletePluginEntrys()
    {
        $sql = <<<'EOD'
SET @advancedMenuPluginId = (SELECT id FROM `s_core_plugins` WHERE `name` = 'AdvancedMenu' LIMIT 1);
SET @advancedMenuFormId = (SELECT id FROM `s_core_config_forms` WHERE `plugin_id` = @advancedMenuPluginId LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE `s_core_config_element_translations` FROM `s_core_config_element_translations` JOIN `s_core_config_elements` ON `s_core_config_element_translations`.`element_id` = `s_core_config_elements`.`id` WHERE `s_core_config_elements`.`form_id` = @advancedMenuFormId;
DELETE `s_core_config_values` FROM `s_core_config_values` JOIN `s_core_config_elements` ON `s_core_config_values`.`element_id` = `s_core_config_elements`.`id` WHERE `s_core_config_elements`.`form_id` = @advancedMenuFormId;
DELETE FROM `s_core_config_elements` WHERE `form_id` = @advancedMenuFormId;
DELETE FROM `s_core_config_form_translations` WHERE `form_id` = @advancedMenuFormId;
DELETE FROM `s_core_config_forms` WHERE `id` = @advancedMenuFormId;
DELETE FROM `s_core_plugins` WHERE `id` = @advancedMenuPluginId;
DELETE FROM s_core_subscribes WHERE pluginID = @advancedMenuPluginId;
EOD;
        $this->addSql($sql);
    }

    private function createAdvancedMenuForm()
    {
        $sql = <<<'EOD'
SET @parentForm = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Frontend' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `plugin_id`) VALUES
(@parentForm , 'AdvancedMenu', 'Erweitertes Menü', NULL, 0, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @advancedMenuFormId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'AdvancedMenu' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`, `description`)
VALUES (@advancedMenuFormId, '2', 'Advanced menu', NULL);
EOD;
        $this->addSql($sql);
    }

    private function createAdvancedMenuFormElements()
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`) 
VALUES 
(@advancedMenuFormId, 'show', 'i:0;', 'Menü anzeigen', NULL, 'checkbox', '0', '0', '1', 'a:0:{}'),
(@advancedMenuFormId, 'levels', 'i:3;', 'Anzahl Ebenen', NULL, 'text', '0', '0', '1', 'a:0:{}'),
(@advancedMenuFormId, 'hoverDelay', 'i:250;', 'Hover Verzögerung (ms)', NULL, 'number', '0', '0', '0', 'a:0:{}'),
(@advancedMenuFormId, 'columnAmount', 'i:2;', 'Breite des Teasers', NULL, 'select', '0', '0', '1', 'a:2:{s:5:"store";a:5:{i:0;a:2:{i:0;i:0;i:1;s:2:"0%";}i:1;a:2:{i:0;i:1;i:1;s:3:"25%";}i:2;a:2:{i:0;i:2;i:1;s:3:"50%";}i:3;a:2:{i:0;i:3;i:1;s:3:"75%";}i:4;a:2:{i:0;i:4;i:1;s:4:"100%";}}s:8:"editable";b:0;}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @elementShowId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'show' AND `form_id` = @advancedMenuFormId LIMIT 1);
SET @elementLevelsId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'levels' AND `form_id` = @advancedMenuFormId LIMIT 1);
SET @elementHoverDelayId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'hoverDelay' AND `form_id` = @advancedMenuFormId LIMIT 1);
SET @elementColumnAmountId = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'columnAmount' AND `form_id` = @advancedMenuFormId LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
VALUES 
(@elementShowId, 2, 'Show menu', NULL),
(@elementLevelsId, 2, 'Category levels', NULL),
(@elementHoverDelayId, 2, 'Hover delay (ms)', NULL),
(@elementColumnAmountId, 2, 'Teaser width', NULL);
EOD;
        $this->addSql($sql);
    }

    private function updateAdvancedMenuForm()
    {
        $sql = <<<'EOD'
SET @advancedMenuPluginId = (SELECT id FROM `s_core_plugins` WHERE `name` = 'AdvancedMenu' LIMIT 1);
SET @advancedMenuFormId = (SELECT id FROM `s_core_config_forms` WHERE `plugin_id` = @advancedMenuPluginId LIMIT 1);
SET @cachingElementId = (SELECT id FROM `s_core_config_elements` WHERE `form_id` = @advancedMenuFormId AND `name` = 'caching' LIMIT 1);
SET @cachetimeElementId = (SELECT id FROM `s_core_config_elements` WHERE `form_id` = @advancedMenuFormId AND `name` = 'cachetime' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
UPDATE `s_core_config_forms` SET `plugin_id` = NULL, `description` = NULL WHERE `name` = 'AdvancedMenu';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_config_element_translations` WHERE `element_id` = @cachingElementId OR `element_id` = @cachetimeElementId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_config_elements` WHERE `id` = @cachingElementId OR `id` = @cachetimeElementId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_config_values` WHERE `element_id` = @cachingElementId OR `element_id` = @cachetimeElementId;
EOD;
        $this->addSql($sql);
    }

    private function addAdvancedMenuToCache()
    {
        $statement = $this->connection->prepare("SELECT * FROM s_core_config_elements WHERE name = 'cacheControllers'");
        $statement->execute();
        $config = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($config)) {
            return;
        }

        $value = unserialize($config['value']);
        $value .= '
widgets/advancedMenu 14400';

        $statement = $this->connection->prepare('UPDATE s_core_config_elements SET value = ? WHERE id = ?');
        $statement->execute([serialize($value), $config['id']]);

        $statement = $this->connection->prepare('SELECT * FROM s_core_config_values WHERE element_id = ?');
        $statement->execute([$config['id']]);
        $values = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($values)) {
            return;
        }

        foreach ($values as $shopValue) {
            if (empty($shopValue) || empty($shopValue['value'])) {
                continue;
            }

            $value = unserialize($shopValue['value']);
            $value .= '
widgets/advancedMenu 14400';

            $statement = $this->connection->prepare('UPDATE s_core_config_values SET value = ? WHERE id = ?');
            $statement->execute([serialize($value), $shopValue['id']]);
        }
    }
}
