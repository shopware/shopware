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

class Migrations_Migration910 extends AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @cookieFormParent = (SELECT id FROM s_core_config_forms WHERE `name` LIKE 'Frontend' AND `label` LIKE 'Storefront');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE s_core_config_forms 
                (`parent_id`, `name`, `label`, `description`, `position`, `plugin_id`) 
            VALUE 
                (@cookieFormParent, 'CookiePermission', 'Cookie Hinweis', NULL, 0, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @cookieFormId = (SELECT id FROM s_core_config_forms WHERE `name` LIKE 'CookiePermission' AND `label` LIKE 'Cookie Hinweis');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE s_core_config_form_translations
                (`form_id`, `locale_id`, `label`, `description`) 
            VALUE
                (@cookieFormId, '2', 'Cookie hint', null)
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE s_core_config_elements 
                (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`) 
            VALUE 
                (@cookieFormId, 'show_cookie_note', 'b:0;', 'Cookie Hinweis anzeigen', 'Wenn diese Option aktiv ist, wird eine Hinweismeldung angezeigt die den Nutzer über die Cookie-Richtlinien informiert. Der Inhalt kann über das Textbausteinmodul editiert werden.', 'boolean', 0, 0, 1, NULL),
                (@cookieFormId, 'data_privacy_statement_link', 's:0:"";', 'Link zur Datenschutzerklärung', NULL, 'text', 0, 0, 1, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @showHideCookie = (SELECT id FROM `s_core_config_elements` WHERE `name` LIKE 'show_cookie_note');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @privacyLink = (SELECT id FROM `s_core_config_elements` WHERE `name` LIKE 'data_privacy_statement_link');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_element_translations` 
                (`element_id`, `locale_id`, `label`, `description`)
            VALUES
                (@showHideCookie, '2', 'Show cookie hint', 'If this option is active, a notification message will be displayed informing the user of the cookie guidelines. The content can be edited via the text editor module.'),
                (@privacyLink, '2', 'Link to the data privacy statement', NULL);
EOD;
        $this->addSql($sql);
    }
}
