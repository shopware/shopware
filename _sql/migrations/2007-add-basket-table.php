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

class Migrations_Migration2007 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql(<<<EOD
CREATE TABLE `s_cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `content` LONGTEXT COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD
);

        $this->addSql(<<<EOD
CREATE TABLE `s_cart_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8_unicode_ci NOT NULL,
  `order_time` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD
        );

        $this->addSql('ALTER TABLE `s_core_paymentmeans` ADD `risk_rules` longtext COLLATE utf8_unicode_ci NULL DEFAULT NULL');

        $this->addSql('ALTER TABLE `s_core_shops` ADD `payment_id` int(11) NOT NULL');
        $this->addSql('ALTER TABLE `s_core_shops` ADD `dispatch_id` int(11) NOT NULL');
        $this->addSql('ALTER TABLE `s_core_shops` ADD `country_id` int(11) NOT NULL');

        $this->addSql('UPDATE s_core_shops SET payment_id = (SELECT id FROM s_core_paymentmeans LIMIT 1)');
        $this->addSql('UPDATE s_core_shops SET dispatch_id = (SELECT id FROM s_premium_dispatch LIMIT 1)');
        $this->addSql('UPDATE s_core_shops SET country_id = (SELECT id FROM s_core_countries LIMIT 1)');
    }
}
