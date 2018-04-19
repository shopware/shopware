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

use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;

class Shopware_Tests_Models_ShopRepositoryTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var \Shopware\Models\Shop\Repository
     */
    private $shopRepository;

    private $mainShop;

    private $mainShopBackup;

    public function setUp()
    {
        parent::setUp();

        $this->shopRepository = Shopware()->Models()->getRepository(Shop::class);
        $this->mainShop = Shopware()->Db()->fetchRow('SELECT * FROM s_core_shops WHERE id = 1');

        // Backup and change existing main shop
        $this->mainShopBackup = Shopware()->Db()->fetchRow('SELECT * FROM s_core_shops WHERE id = 1');

        Shopware()->Db()->update('s_core_shops', [
            'host' => 'fallbackhost',
        ], 'id = 1');

        $this->mainShop = Shopware()->Db()->fetchRow('SELECT * FROM s_core_shops WHERE id = 1');

        // Create test shops
        $sql = "
            INSERT IGNORE INTO `s_core_shops` (`id`, `main_id`, `name`, `title`, `position`, `host`, `base_path`, `base_url`, `hosts`, `secure`, `template_id`, `document_template_id`, `category_id`, `locale_id`, `currency_id`, `customer_group_id`, `fallback_id`, `customer_scope`, `default`, `active`) VALUES
            (100, 1, 'testShop1', 'Testshop', 0, NULL, NULL, ?, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (101, 1, 'testShop2', 'Testshop', 0, NULL, NULL, ?, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (102, 1, 'testShop3', 'Testshop', 0, NULL, NULL, ?, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (103, 1, 'testShop4', 'Testshop', 0, NULL, NULL, ?, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (104, 1, 'testShop5', 'Testshop', 0, NULL, NULL, ?, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (200, NULL, 'testShopPath', 'Testshop', 0, NULL, '/path', '', '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1),
            (201, NULL, 'testShopPath2', 'Testshop', 0, NULL, '/path', NULL, '', 0, 11, 11, 11, 2, 1, 1, 2, 0, 0, 1);
        ";
        Shopware()->Db()->query($sql, [
            $this->mainShop['base_path'] . '/english',
            $this->mainShop['base_path'] . '/en/uk',
            $this->mainShop['base_path'] . '/en',
            $this->mainShop['base_path'] . '/en/us',
            $this->mainShop['base_path'] . '/aus/en',
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        // Remove test data and restore previous status
        Shopware()->Db()->exec('DELETE FROM s_core_shops WHERE id IN (100, 101, 102, 103, 104, 200, 201);');
        unset($this->mainShopBackup['id']);
        Shopware()->Db()->update('s_core_shops', $this->mainShopBackup, 'id = 1');
    }

    /**
     * Ensures that getActiveByRequest() returns the correct shop
     *
     * @ticket SW-7774
     * @ticket SW-6768
     */
    public function getActiveByRequestDataProvider()
    {
        $mainShop = Shopware()->Container()->get('dbal_connection')
            ->executeQuery('SELECT * FROM s_core_shops WHERE id = 1')
            ->fetch();

        return [
            // Tests copied for SW-6768
            [$mainShop['base_path'] . '/en', 'testShop3'],

            //check virtual url with superfluous / like localhost/en/
            [$mainShop['base_path'] . '/en/', 'testShop3'],

            //check virtual url with direct controller call like localhost/en/blog
            [$mainShop['base_path'] . '/en/blog', 'testShop3'],

            //check base shop with direct controller call like localhost/en/blog
            [$mainShop['base_path'] . '/blog', $mainShop['name']],

            //check without virtual url but an url with the same beginning like localhost/entsorgung
            [$mainShop['base_path'] . '/entsorgung', $mainShop['name']],

            //check different virtual url with like localhost/ente
            [$mainShop['base_path'] . '/en/uk', 'testShop2'],

            //check without virtual url it has to choose the main shop instead of the language shop without the virtual url
            [$mainShop['base_path'], $mainShop['name']],

            // These are just some basic urls
            [$mainShop['base_path'] . '', $mainShop['name']],
            [$mainShop['base_path'] . '/', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/en', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/entsorgung', $mainShop['name']],
            [$mainShop['base_path'] . '/fenglish', $mainShop['name']],
            [$mainShop['base_path'] . '/english', 'testShop1'],
            [$mainShop['base_path'] . '/en', 'testShop3'],

            // These cover the cases affected by the ticket, where the base_path would be present in the middle of the url
            [$mainShop['base_path'] . '/foo/english', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/en', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/enaaa/', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/uk/', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/en/uk/', $mainShop['name']],
            [$mainShop['base_path'] . '/foo/en/uk/things', $mainShop['name']],

            // And these are some extreme cases, due to the overlapping of urls
            [$mainShop['base_path'] . '/en/ukfoooo', 'testShop3'],
            [$mainShop['base_path'] . '/en/uk', 'testShop2'],
            [$mainShop['base_path'] . '/en', 'testShop3'],
            [$mainShop['base_path'] . '/en/uk/things', 'testShop2'],

            // Tests for secure
            [$mainShop['base_path'] . '/en/us', 'testShop4'],
            [$mainShop['base_path'] . '/en/us', 'testShop4'],
            [$mainShop['base_path'] . '/en/ukfoooo', 'testShop3'],
            [$mainShop['base_path'] . '/en/ukfoooo', 'testShop3'],
            [$mainShop['base_path'] . '/en/uk', 'testShop2'],
            [$mainShop['base_path'] . '/en/uk', 'testShop2'],
            [$mainShop['base_path'] . '/en/uk/things', 'testShop2'],
            [$mainShop['base_path'] . '/en/uk/things', 'testShop2'],
        ];
    }

    /**
     * helper method to call the getActiveByRequest Method with different params
     *
     * @dataProvider getActiveByRequestDataProvider
     *
     * @param string $url
     * @param string $shopName
     */
    public function testGetActiveByRequest($url, $shopName)
    {
        $request = new Enlight_Controller_Request_RequestTestCase();
        $request->setHttpHost($this->mainShop['host']);
        $request->setRequestUri($url);

        $shop = $this->shopRepository->getActiveByRequest($request);

        $this->assertNotNull($shop);
        $this->assertEquals($shopName, $shop->getName());
    }

    public function getMultiShopLocationTestData()
    {
        return [
            ['test.in', 'fr.test.in'],
            ['test.in', 'nl.test.in'],
            ['2test.in', '2fr.test.in'],
            ['2test.in', '2nl.test.in'],
        ];
    }

    /**
     * @dataProvider getMultiShopLocationTestData
     * @ticket SW-4858
     */
    public function testMultiShopLocation($host, $alias)
    {
        Shopware()->Container()->reset('Template');

        // Create test shops
        $sql = "
            INSERT IGNORE INTO `s_core_shops` (
              `id`, `main_id`, `name`, `title`, `position`,
              `host`, `base_path`, `base_url`, `hosts`,
              `secure`,
              `template_id`, `document_template_id`, `category_id`,
              `locale_id`, `currency_id`, `customer_group_id`,
              `fallback_id`, `customer_scope`, `default`, `active`
            ) VALUES (
              10, NULL, 'Testshop 2', 'Testshop 2', 0,
              '2test.in', NULL, NULL, '2fr.test.in\\n2nl.test.in\\n',
              0,
              11, 11, 11, 2, 1, 1, 2, 0, 0, 1
            ), (
              11, NULL, 'Testshop 1', 'Testshop 1', 0,
              'test.in', NULL, NULL, 'fr.test.in\\nnl.test.in\\n',
              0,
              11, 11, 11, 2, 1, 1, 2, 0, 0, 1
            );
        ";
        Shopware()->Db()->exec($sql);

        $request = $this->Request();
        $this->Request()->setHttpHost($alias);
        $shop = $this->shopRepository->getActiveByRequest($request);

        $this->assertNotNull($shop);
        $this->assertEquals($host, $shop->getHost());

        // Delete test shops
        $sql = 'DELETE FROM s_core_shops WHERE id IN (10, 11);';
        Shopware()->Db()->exec($sql);
    }

    /**
     * Tests the shop duplication bug caused by the detaching the shop entity
     * in the obsolete Shopware\Models\Shop\Repsoitory::fixActive()
     */
    public function testShopDuplication()
    {
        // Get inital number of shops
        $numberOfShopsBefore = Shopware()->Db()->fetchOne('SELECT count(*) FROM s_core_shops');

        // Load arbitrary order
        $order = Shopware()->Models()->getRepository(Order::class)->find(57);

        // Modify order entitiy to trigger an update action, when the entity is flushed to the database
        $order->setComment('Dummy');

        // Send order status mail to customer, this will invoke the fixActive()-method
        $mail = Shopware()->Modules()->Order()->createStatusMail($order->getId(), 7);
        Shopware()->Modules()->Order()->sendStatusMail($mail);

        // Flush changes changed order to the database
        Shopware()->Models()->flush($order);

        // Get current number of shops
        $numberOfShopsAfter = Shopware()->Db()->fetchOne('SELECT count(*) FROM s_core_shops');

        // Check that the number of shops has not changed
        $this->assertSame($numberOfShopsBefore, $numberOfShopsAfter);

        // Clean up comment
        $order->setComment('');
        Shopware()->Models()->flush($order);
    }

    public function testGetDbalShopsQueryWithEmptyUrl()
    {
        $query = $this->invokeMethod($this->shopRepository, 'getDbalShopsQuery');
        $shops = $query->andWhere('shop.id >= 200 AND shop.id < 205')
            ->execute()
            ->fetchAll();

        foreach ($shops as $shop) {
            $this->assertNotFalse($shop);
            $this->assertEquals($shop['base_url'], $shop['base_path']);
        }
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
