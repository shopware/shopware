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
 * tests the base price calculation
 *
 * @ticket SW-7204
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Modules_Articles_TestBasePriceCalculation extends Enlight_Components_Test_Plugin_TestCase
{
    /**
     * Set up test case, fix demo data where needed
     */
    public function setUp()
    {
        parent::setUp();

        $sql = "UPDATE `s_articles_details` SET `kind` = '1' WHERE `ordernumber` = 'SW10002.1'";
        Shopware()->Db()->query($sql);

        $sql = "UPDATE `s_articles_details` SET `kind` = '2' WHERE `ordernumber` = 'SW10002.3'";
        Shopware()->Db()->query($sql);
    }

    /**
     * Cleaning up testData
     */
    protected function tearDown()
    {
        parent::tearDown();

//        // Restore old main detail
        $sql = "UPDATE `s_articles_details` SET `kind` = '2' WHERE `ordernumber` = 'SW10002.1'";
        Shopware()->Db()->query($sql);

        $sql = "UPDATE `s_articles_details` SET `kind` = '1' WHERE `ordernumber` = 'SW10002.3'";
        Shopware()->Db()->query($sql);
    }

    /**
     * test the calculation and the returning of the cheapest base price data
     */
    public function testCalculateCheapestBasePriceData()
    {
        $cheapestBasePriceData = Shopware()->Modules()->Articles()->calculateCheapestBasePriceData('19,99', 2, 'EK', 1);
        $this->assertEquals(0.5, $cheapestBasePriceData['purchaseunit']);
        $this->assertEquals(1, $cheapestBasePriceData['referenceunit']);
        $this->assertEquals(39.98, $cheapestBasePriceData['referenceprice']);
        $this->assertEquals('Liter', $cheapestBasePriceData['sUnit']['description']);

        $cheapestBasePriceData = Shopware()->Modules()->Articles()->calculateCheapestBasePriceData('10,95', 5, 'EK', 1);
        $this->assertEquals(0.2, $cheapestBasePriceData['purchaseunit']);
        $this->assertEquals(1, $cheapestBasePriceData['referenceunit']);
        $this->assertEquals(54.75, $cheapestBasePriceData['referenceprice']);
        $this->assertEquals('Liter', $cheapestBasePriceData['sUnit']['description']);
    }

    /**
     * test just to calculate the right reference price
     */
    public function testCalculateReferencePrice()
    {
        $testData = [
            ['price' => 19, 'purchaseUnit' => 0.7, 'referenceUnit' => 1],
            ['price' => '199,99', 'purchaseUnit' => 0.7, 'referenceUnit' => 3],
            ['price' => 19999.99, 'purchaseUnit' => '0.999', 'referenceUnit' => '1.9'],
            ['price' => '19999,89', 'purchaseUnit' => 0.999, 'referenceUnit' => 1],
            ['price' => '0,139', 'purchaseUnit' => 99, 'referenceUnit' => 1],
        ];
        $expectedData = [
            27.142857142857,
            857.1,
            38038.019019019,
            20019.90990991,
            0.0014040404040404,
        ];
        foreach ($testData as $key => $data) {
            $referencePrice = Shopware()->Modules()->Articles()->calculateReferencePrice(
                $data['price'],
                $data['purchaseUnit'],
                $data['referenceUnit']
            );
            $this->assertEquals($expectedData[$key], $referencePrice);
        }
    }

    /**
     * test to get the cheapest variant data
     */
    public function testGetCheapestVariant()
    {
        //articleIds
        $testData = [122, 2];
        $expectedData = [
            ['purchaseunit' => 0.2000, 'referenceunit' => 1.000],
            ['purchaseunit' => 0.5000, 'referenceunit' => 1.000],
        ];
        foreach ($testData as $key => $data) {
            $cheapestVariantData = Shopware()->Modules()->Articles()->getCheapestVariant($data, 'EK', 1);
            $this->assertEquals($expectedData[$key]['purchaseunit'], $cheapestVariantData['purchaseunit']);
            $this->assertEquals($expectedData[$key]['referenceunit'], $cheapestVariantData['referenceunit']);
            $cheapestVariantData = Shopware()->Modules()->Articles()->getCheapestVariant($data, 'EK', 0);
            $this->assertEquals($expectedData[$key]['purchaseunit'], $cheapestVariantData['purchaseunit']);
            $this->assertEquals($expectedData[$key]['referenceunit'], $cheapestVariantData['referenceunit']);
        }
    }

    /**
     * set the main variant to a bigger prices variant and check if the base price data of the main article is returned
     */
    public function testsGetArticleById()
    {
        $this->dispatch('/');
        $articleDetailData = Shopware()->Modules()->Articles()->sGetArticleById(2);
        $this->assertEquals(39.98, $articleDetailData['referenceprice']);
    }

    /**
     * test the right base price result of the sGetPromotionById
     */
    public function testsGetPromotionById()
    {
        $this->dispatch('/');
        $articleData = Shopware()->Modules()->Articles()->sGetPromotionById('fix', 0, 2);
        $this->assertEquals(1, $articleData['referenceunit']);
        $this->assertEquals(0.5, $articleData['purchaseunit']);
        $this->assertEquals(39.98, $articleData['referenceprice']);

        $this->dispatch('/');
        $articleData = Shopware()->Modules()->Articles()->sGetPromotionById('fix', 0, 5);
        $this->assertEquals(0.2, $articleData['purchaseunit']);
        $this->assertEquals(1, $articleData['referenceunit']);
        $this->assertEquals(54.75, $articleData['referenceprice']);
    }

    /**
     * test the right base price result of the sGetProductByOrderNumber
     */
    public function testsGetProductByOrderNumber()
    {
        $this->dispatch('/');
        $articleData = Shopware()->Modules()->Articles()->sGetProductByOrdernumber('SW10002.2');
        $this->assertEquals(5, $articleData['purchaseunit']);
        $this->assertEquals(1, $articleData['referenceunit']);
        $this->assertEquals(39.8, $articleData['referenceprice']);

        $articleData = Shopware()->Modules()->Articles()->sGetProductByOrdernumber('SW10003');
        $this->assertEquals(0.7, $articleData['purchaseunit']);
        $this->assertEquals(1, $articleData['referenceunit']);
        $this->assertEquals(21.357142857143, $articleData['referenceprice']);
    }
}
