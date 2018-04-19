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

namespace Shopware\Tests\Functional\Plugins\Core\MarketingAggregate\Components;

use Shopware\Tests\Functional\Plugins\Core\MarketingAggregate\AbstractMarketing;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class AlsoBoughtTest extends AbstractMarketing
{
    protected $orderData;

    public function setUp()
    {
        parent::setUp();

        $this->Db()->query('DELETE FROM s_articles_also_bought_ro ');

        $this->orderData = $this->Db()->fetchAll('SELECT * FROM s_order_details');

        $this->Db()->query('DELETE FROM s_order_details');

        $this->Db()->query("
            INSERT INTO `s_order_details` (`id`, `orderID`, `ordernumber`, `articleID`, `articleordernumber`, `price`, `quantity`, `name`, `status`, `shipped`, `shippedgroup`, `releasedate`, `modus`, `esdarticle`, `taxID`, `tax_rate`, `config`) VALUES
            (178, 52, '0', 227, 'SW10002841', 35.99, 31, 'Aufschlag bei Zahlungsarten', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (179, 52, '0', 145, 'SW10145', 17.99, 1, 'Mütze Vintage Driver', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (180, 52, '0', 248, 'SW100755036993', 74.99, 9, 'Versandkosten nach Gewicht', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (181, 52, '0', 0, 'SHIPPINGDISCOUNT', -2, 1, 'Warenkorbrabatt', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (182, 52, '0', 0, 'sw-payment', -180.659, 1, 'Abschlag für Zahlungsart', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (187, 54, '0', 211, 'SW10221', 50, 1, 'Prämienartikel ab 250 Euro Warenkorb Wert', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (188, 54, '0', 0, 'SHIPPINGDISCOUNT', -2, 1, 'Warenkorbrabatt', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (189, 54, '0', 1, 'GUTABS', -5, 1, 'Gutschein', 0, 0, 0, '0000-00-00', 2, 0, 0, 19, ''),
            (190, 54, '0', 0, 'sw-payment', -4.3, 1, 'Abschlag für Zahlungsart', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (201, 57, '20002', 220, 'SW10001', 35.99, 1, 'Versandkostenfreier Artikel', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (202, 57, '20002', 227, 'SW10002841', 35.99, 1, 'Aufschlag bei Zahlungsarten', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (203, 57, '20002', 219, 'SW10185', 54.9, 1, 'Express Versand', 0, 0, 0, '0000-00-00', 0, 0, 1, 19, ''),
            (204, 57, '20002', 197, 'SW10196', 34.99, 2, 'ESD Download Artikel', 0, 0, 0, '0000-00-00', 0, 1, 1, 19, ''),
            (205, 57, '20002', 0, 'sw-payment-absolute', 5, 1, 'Zuschlag für Zahlungsart', 0, 0, 0, '0000-00-00', 4, 0, 0, 19, ''),
            (255, 120, '20007', 178, 'SW10178', 19.95, 1, 'Strandtuch \"Ibiza\"', 0, 0, 0, '0000-00-00', 0, 0, 0, 19, ''),
            (256, 120, '20007', 175, 'SW10175', 59.99, 1, 'Strandtuch Sunny', 0, 0, 0, '0000-00-00', 0, 0, 0, 19, ''),
            (257, 120, '20007', 162, 'SW10162.1', 23.99, 1, 'Sommer-Sandale Pink 36', 0, 0, 0, '0000-00-00', 0, 0, 0, 19, ''),
            (258, 120, '20007', 197, 'SW10196', 29.99, 3, 'ESD Download Artikel', 0, 0, 0, '0000-00-00', 0, 1, 0, 19, ''),
            (259, 120, '20007', 0, 'SHIPPINGDISCOUNT', -2, 1, 'Warenkorbrabatt', 0, 0, 0, '0000-00-00', 0, 0, 0, 19, '');
        ");

        $this->AlsoBought()->initAlsoBought();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->Db()->query('DELETE FROM s_order_details');

        foreach ($this->orderData as $orderData) {
            $this->Db()->insert('s_order_details', $orderData);
        }
    }

    public function testInitAlsoBought()
    {
        $this->assertCount(30, $this->getAllAlsoBought());
    }

    public function testRefreshBoughtArticles()
    {
        $combinations = $this->getAllAlsoBought();
        foreach ($combinations as $combination) {
            $this->AlsoBought()->refreshBoughtArticles(
                $combination['article_id'],
                $combination['related_article_id']
            );
            $updated = $this->getAllAlsoBought(
                ' WHERE article_id = ' . $combination['article_id'] .
                ' AND related_article_id = ' . $combination['related_article_id']
            );
            $updated = $updated[0];

            $this->assertNotEmpty($updated);
            $this->assertEquals($combination['sales'] + 1, $updated['sales']);
        }
    }

    protected function getAllAlsoBought($condition = '')
    {
        return $this->Db()->fetchAll('SELECT * FROM s_articles_also_bought_ro ' . $condition);
    }
}
