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

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Controllers_Backend_CanceledOrderTest extends Enlight_Components_Test_Plugin_TestCase
{
    const FIRST_DUMMY_SESSION_ID = '1231231231231231231231231231231231231320';
    const SECOND_DUMMY_SESSION_ID = '1231231231231231231231231231231231231321';

    /**
     * Set up test case, fix demo data where needed
     */
    public function setUp()
    {
        parent::setUp();

        // insert test order
        $sql = "
              INSERT INTO `s_order_basket` (`sessionID`, `userID`, `articlename`, `articleID`, `ordernumber`, `shippingfree`, `quantity`, `price`, `netprice`, `tax_rate`, `datum`, `modus`, `esdarticle`, `partnerID`, `lastviewport`, `useragent`, `config`, `currencyFactor`) VALUES
                (:firstSession, 0, 'Sonnenbrille Red', 170, 'SW10170', 0, 4, 39.95, 33.571428571429, 19, '2101-09-11 11:49:54', 0, 0, '', 'index', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:22.0) Gecko/20100101 Firefox/22.0 FirePHP/0.7.2', '', 1),
                (:firstSession, 0, 'Fliegenklatsche grün', 98, 'SW10101', 0, 1, 0.79, 0.66386554621849, 19, '2101-09-11 11:50:02', 0, 0, '', 'index', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:22.0) Gecko/20100101 Firefox/22.0 FirePHP/0.7.2', '', 1),
                (:firstSession, 0, 'Bumerang', 245, 'SW10236', 0, 1, 20, 16.806722689076, 19, '2101-09-11 11:50:13', 0, 0, '', 'index', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:22.0) Gecko/20100101 Firefox/22.0 FirePHP/0.7.2', '', 1),
                (:secondSession, 0, 'Dartscheibe Circle', 240, 'SW10231', 0, 1, 49.99, 42.008403361345, 19, '2101-09-11 11:50:17', 0, 0, '', '', '', '', 1),
                (:secondSession, 0, 'Dartpfeil Steel Atomic', 241, 'SW10232', 0, 1, 14.99, 12.596638655462, 19, '2101-09-11 11:50:20', 0, 0, '', '', '', '', 1),
                (:firstSession, 0, 'Dart Automat Standgerät', 239, 'SW10230', 0, 1, 2499, 2100, 19, '2101-09-10 11:50:22', 0, 0, '', 'index', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:22.0) Gecko/20100101 Firefox/22.0 FirePHP/0.7.2', '', 1),
                (:firstSession, 0, 'Warenkorbrabatt', 0, 'SHIPPINGDISCOUNT', 0, 1, -2, -1.68, 19, '2101-09-10 11:50:22', 4, 0, '', 'index', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:22.0) Gecko/20100101 Firefox/22.0 FirePHP/0.7.2', '', 1);
        ";

        Shopware()->Db()->query($sql, ['firstSession' => self::FIRST_DUMMY_SESSION_ID, 'secondSession' => self::SECOND_DUMMY_SESSION_ID]);
    }

    /**
     * Cleaning up testData
     */
    protected function tearDown()
    {
        parent::tearDown();

        $sql = '
            DELETE FROM `s_order_basket` WHERE `sessionID` = :firstSession;
            DELETE FROM `s_order_basket` WHERE `sessionID` = :secondSession;
        ';

        Shopware()->Db()->query($sql, ['firstSession' => self::FIRST_DUMMY_SESSION_ID, 'secondSession' => self::SECOND_DUMMY_SESSION_ID]);
    }

    /**
     * test if the canceled order statistic returns the right values
     *
     * @ticket SW-6624
     */
    public function testCanceledOrderSummary()
    {
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();

        $this->dispatch('backend/CanceledOrder/getBasket?fromDate=2101-09-10T00%3A00%3A00&toDate=2101-09-11T00%3A00%3A00');
        $data = $this->View()->getAssign('data');

        $firstRow = $data[0];
        $this->assertEquals('2101-09-10', $firstRow['date']);
        $this->assertEquals('2499', $firstRow['price']);
        $this->assertEquals('2499', $firstRow['average']);
        $this->assertEquals('1', $firstRow['number']);
        $this->assertEquals('2101', $firstRow['year']);
        $this->assertEquals('9', $firstRow['month']);

        $secondRow = $data[1];
        $this->assertEquals('2101-09-11', $secondRow['date']);
        $this->assertEquals('125.72', $secondRow['price']);
        $this->assertEquals('25.144', $secondRow['average']);
        $this->assertEquals('2', $secondRow['number']);
        $this->assertEquals('2101', $secondRow['year']);
        $this->assertEquals('9', $secondRow['month']);
    }
}
