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

use Shopware\Models\Analytics\Repository;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Controllers_Backend_AnalyticsTest extends Enlight_Components_Test_Controller_TestCase
{
    /** @var Shopware\Models\Analytics\Repository */
    private $repository;

    private $userId;
    private $customerNumber;
    private $articleId;
    private $categoryId;
    private $orderNumber;
    private $articleDetailId;
    private $orderIds;
    private $addressId;

    /**
     * Standard set up for every test - just disable auth
     */
    public function setUp()
    {
        parent::setUp();

        // disable auth and acl
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();

        $this->repository = new Repository(Shopware()->Models()->getConnection(), Shopware()->Events());

        $this->orderNumber = uniqid('SW');
        $this->articleId = 0;
        $this->userId = 0;
    }

    public function tearDown()
    {
        $this->removeDemoData();
    }

    public function testGetVisitorImpressions()
    {
        $this->createVisitors();

        $result = $this->repository->getVisitorImpressions(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            [
                [
                    'property' => 'datum',
                    'direction' => 'ASC',
                ],
            ],
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'datum' => '2013-06-01',
                    'desktopImpressions' => 300,
                    'tabletImpressions' => 0,
                    'mobileImpressions' => 0,
                    'totalImpressions' => 300,
                    'desktopVisits' => 10,
                    'tabletVisits' => 0,
                    'mobileVisits' => 0,
                    'totalVisits' => 10,
                    'desktopImpressions1' => 300,
                    'tabletImpressions1' => 0,
                    'mobileImpressions1' => 0,
                    'totalImpressions1' => 300,
                    'desktopVisits1' => 10,
                    'tabletVisits1' => 0,
                    'mobileVisits1' => 0,
                    'totalVisits1' => 10,
                ],
                [
                    'datum' => '2013-06-15',
                    'desktopImpressions' => 500,
                    'tabletImpressions' => 0,
                    'mobileImpressions' => 0,
                    'totalImpressions' => 500,
                    'desktopVisits' => 20,
                    'tabletVisits' => 0,
                    'mobileVisits' => 0,
                    'totalVisits' => 20,
                    'desktopImpressions1' => 500,
                    'tabletImpressions1' => 0,
                    'mobileImpressions1' => 0,
                    'totalImpressions1' => 500,
                    'desktopVisits1' => 20,
                    'tabletVisits1' => 0,
                    'mobileVisits1' => 0,
                    'totalVisits1' => 20,
                ],
            ]
        );
    }

    public function testGetOrdersOfCustomers()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getOrdersOfCustomers(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderTime' => '2013-06-01',
                    'isNewCustomerOrder' => 1,
                    'salutation' => 'mr',
                    'userId' => $this->userId,
                ],
            ]
        );
    }

    public function testGetReferrerRevenue()
    {
        $this->createCustomer();
        $this->createOrders();

        $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->getActiveDefault();
        $shop->registerResources();

        $result = $this->repository->getReferrerRevenue(
            $shop,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'turnover' => 1000.00,
                    'userID' => $this->userId,
                    'referrer' => 'http://www.google.de/',
                    'firstLogin' => '2013-06-01',
                    'orderTime' => '2013-06-01',
                ],
            ]
        );
    }

    public function testGetPartnerRevenue()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getPartnerRevenue(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'turnover' => 1000,
                    'partner' => null,
                    'trackingCode' => 'PHPUNIT_PARTNER',
                    'partnerId' => null,
                ],
            ]
        );
    }

    public function testGetProductSales()
    {
        $this->createCustomer();
        $this->createArticle();
        $this->createOrders();

        $result = $this->repository->getProductSales(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'sales' => 1,
                    'name' => 'PHPUNIT ARTICLE',
                    'ordernumber' => $this->orderNumber,
                ],
            ]
        );
    }

    public function testGetProductImpressions()
    {
        $this->createArticle();
        $this->createImpressions();

        $result = $this->repository->getProductImpressions(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            [
                [
                    'property' => 'articleId',
                    'direction' => 'ASC',
                ],
            ],
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'articleId' => $this->articleId,
                    'articleName' => 'PHPUNIT ARTICLE',
                    'totalImpressions' => 10,
                    'totalImpressions1' => 10,
                    'desktopImpressions' => 10,
                    'tabletImpressions' => 0,
                    'mobileImpressions' => 0,
                ],
            ]
        );
    }

    public function testGetAgeOfCustomers()
    {
        $this->createCustomer();

        $result = $this->repository->getAgeOfCustomers(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'firstLogin' => '2013-06-01',
                    'birthday' => '1990-01-01',
                    'birthday1' => '1990-01-01',
                ],
            ]
        );
    }

    public function testGetAmountPerHour()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerHour(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'orderCount1' => 1,
                    'turnover' => 1000,
                    'turnover1' => 1000,
                    'displayDate' => 'Saturday',
                    'date' => '1970-01-01 10:00:00',
                ],
            ]
        );
    }

    public function testGetAmountPerWeekday()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerWeekday(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'date' => '2013-06-01',
                ],
            ]
        );
    }

    public function testGetAmountPerCalendarWeek()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerCalendarWeek(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'date' => '2013-05-30',
                ],
            ]
        );
    }

    public function testGetAmountPerMonth()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerMonth(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'date' => '2013-06-04',
                ],
            ]
        );
    }

    public function testGetCustomerGroupAmount()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getCustomerGroupAmount(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'customerGroup' => 'Shopkunden',
                ],
            ]
        );
    }

    public function testGetAmountPerCountry()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerCountry(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'name' => 'Deutschland',
                ],
            ]
        );
    }

    public function testGetAmountPerShipping()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerShipping(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'name' => 'Standard Versand',
                ],
            ]
        );
    }

    public function testGetAmountPerPayment()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerPayment(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'displayDate' => 'Saturday',
                    'name' => 'Lastschrift',
                ],
            ]
        );
    }

    public function testGetSearchTerms()
    {
        $this->createSearchTerms();

        $result = $this->repository->getSearchTerms(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            [
                [
                    'property' => 'countRequests',
                    'direction' => 'ASC',
                ],
            ]
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'countRequests' => 1,
                    'searchterm' => 'phpunit search term',
                    'countResults' => 10,
                    'shop' => null,
                ],
            ]
        );
    }

    public function testGetDailyVisitors()
    {
        $this->createVisitors();

        $result = $this->repository->getDailyVisitors(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                '2013-06-15' => [
                    [
                        'clicks' => 500,
                        'visits' => 20,
                    ],
                ],
                '2013-06-01' => [
                    [
                        'clicks' => 300,
                        'visits' => 10,
                    ],
                ],
            ]
        );
    }

    public function testGetDailyShopVisitors()
    {
        $this->createVisitors();

        $result = $this->repository->getDailyShopVisitors(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                '2013-06-15' => [
                    [
                        'clicks' => 500,
                        'visits' => 20,
                        'visits1' => 20,
                    ],
                ],
                '2013-06-01' => [
                    [
                        'clicks' => 300,
                        'visits' => 10,
                        'visits1' => 10,
                    ],
                ],
            ]
        );
    }

    public function testGetDailyShopOrders()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getDailyShopOrders(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01'),
            ['1']
        );

        $this->assertEquals(
            $result->getData(),
            [
                '2013-06-15' => [
                    [
                        'orderCount' => 0,
                        'orderCount1' => 0,
                        'cancelledOrders' => 1,
                        'cancelledOrders1' => 1,
                    ],
                ],
                '2013-06-01' => [
                    [
                        'orderCount' => 1,
                        'orderCount1' => 1,
                        'cancelledOrders' => 0,
                        'cancelledOrders1' => 0,
                    ],
                ],
            ]
        );
    }

    public function testGetDailyRegistrations()
    {
        $this->createCustomer();

        $result = $this->repository->getDailyRegistrations(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                '2013-06-01' => [
                    [
                        'registrations' => 1,
                        'customers' => 0,
                    ],
                ],
            ]
        );
    }

    public function testGetDailyTurnover()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getDailyTurnover(
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                '2013-06-01' => [
                    [
                        'orderCount' => 1,
                        'turnover' => 1000,
                    ],
                ],
            ]
        );
    }

    public function testGetProductAmountPerManufacturer()
    {
        $this->createCustomer();
        $this->createArticle();
        $this->createOrders();

        $result = $this->repository->getProductAmountPerManufacturer(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'name' => 'shopware AG',
                ],
            ]
        );
    }

    public function testGetVisitedReferrer()
    {
        $this->createReferrer();

        $result = $this->repository->getVisitedReferrer(
            0,
            25,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'count' => 1,
                    'referrer' => 'http://www.google.de/?q=phpunit',
                ],
            ]
        );
    }

    public function testGetReferrerUrls()
    {
        $this->createReferrer();

        $result = $this->repository->getReferrerUrls(
            'phpunit',
            0,
            25
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'count' => 1,
                    'referrer' => 'http://www.google.de/?q=phpunit',
                ],
            ]
        );
    }

    public function testGetReferrerSearchTerms()
    {
        $this->createReferrer();

        $result = $this->repository->getReferrerSearchTerms('phpunit');
        $data = $result->getData();

        $this->assertEquals(
            $data,
            [
                [
                    'count' => 1,
                    'referrer' => 'http://www.google.de/?q=phpunit',
                ],
            ]
        );

        $this->assertEquals(
            $this->getSearchTermFromReferrerUrl($data[0]['referrer']),
            'phpunit'
        );
    }

    public function testGetProductAmountPerCategory()
    {
        $this->createArticle();
        $this->createCategory();
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getProductAmountPerCategory(
            1,
            new DateTime('2013-01-01'),
            new DateTime('2014-01-01')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 1000,
                    'name' => 'phpunit category',
                    'node' => '',
                ],
            ]
        );
    }

    public function testOrderCurrencyFactor()
    {
        $this->createCustomer();
        $this->createOrders();

        $result = $this->repository->getAmountPerHour(
            new DateTime('2014-01-01'),
            new DateTime('2014-02-02')
        );

        $this->assertEquals(
            $result->getData(),
            [
                [
                    'orderCount' => 1,
                    'turnover' => 250,
                    'displayDate' => 'Saturday',
                    'date' => '1970-01-01 10:00:00',
                ],
            ]
        );
    }

    private function createCustomer()
    {
        $this->customerNumber = uniqid(rand());

        Shopware()->Db()->insert(
            's_user',
            [
                'password' => '098f6bcd4621d373cade4e832627b4f6', // md5('test')
                'encoder' => 'md5',
                'email' => uniqid('test') . '@test.com',
                'active' => '1',
                'firstlogin' => '2013-06-01',
                'lastlogin' => '2013-07-01',
                'subshopID' => '1',
                'customergroup' => 'EK',
                'salutation' => 'mr',
                'firstname' => '',
                'lastname' => '',
                'birthday' => '1990-01-01',
            ]
        );
        $this->userId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->insert('s_user_billingaddress', [
            'userID' => $this->userId,
            'company' => 'PHPUNIT',
            'salutation' => 'mr',
            'countryID' => 2,
            'stateID' => 3,
        ]);

        Shopware()->Db()->insert('s_user_addresses', [
            'user_id' => $this->userId,
            'company' => 'PHPUNIT',
            'salutation' => 'mr',
            'firstname' => '',
            'lastname' => '',
            'zipcode' => '',
            'city' => '',
            'country_id' => 2,
            'state_id' => 3,
        ]);
        $this->addressId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->update('s_user', [
            'default_billing_address_id' => $this->addressId,
            'default_shipping_address_id' => $this->addressId,
        ], [
            'id = ?' => $this->userId,
        ]);
    }

    private function createArticle()
    {
        Shopware()->Db()->insert(
            's_articles',
            [
                'supplierID' => 1,
                'name' => 'PHPUNIT ARTICLE',
                'datum' => '2013-06-01',
                'active' => 1,
                'taxID' => 1,
                'main_detail_id' => 0,
            ]
        );
        $this->articleId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->insert(
            's_articles_details',
            [
                'articleID' => $this->articleId,
                'ordernumber' => $this->orderNumber,
                'kind' => 1,
                'active' => 1,
                'instock' => 1,
            ]
        );
        $this->articleDetailId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->update(
            's_articles',
            ['main_detail_id' => $this->articleDetailId],
                'id = ' . $this->articleId
        );
    }

    private function createCategory()
    {
        Shopware()->Db()->insert(
            's_categories',
            [
                'description' => 'phpunit category',
                'parent' => 1,
                'active' => 1,
            ]
        );
        $this->categoryId = Shopware()->Db()->lastInsertId();

        Shopware()->Db()->insert(
            's_articles_categories_ro',
            [
                'articleID' => $this->articleId,
                'categoryID' => $this->categoryId,
            ]
        );
    }

    private function createOrders()
    {
        $this->orderIds = [];

        $orders = [
            [
                'userID' => $this->userId,
                'invoice_amount' => '1000',
                'invoice_amount_net' => '840',
                'ordertime' => '2013-06-01 10:11:12',
                'status' => 0,
                'partnerID' => 'PHPUNIT_PARTNER',
                'referer' => 'http://www.google.de/',
                'subshopID' => 1,
                'currencyFactor' => 1,
                'dispatchID' => 9,
                'language' => 1,
                'paymentID' => 2,
            ],
            [
                'userID' => $this->userId,
                'invoice_amount' => '500',
                'invoice_amount_net' => '420',
                'ordertime' => '2013-06-15 10:11:12',
                'status' => '-1',
                'subshopID' => 1,
                'currencyFactor' => 1,
                'dispatchID' => 9,
                'language' => 1,
                'paymentID' => 2,
            ],
            [
                'userID' => $this->userId,
                'invoice_amount' => '500',
                'invoice_amount_net' => '420',
                'ordertime' => '2014-02-01 10:11:12',
                'status' => 0,
                'subshopID' => 1,
                'currencyFactor' => 2,
                'dispatchID' => 9,
                'language' => 1,
                'paymentID' => 2,
            ],
        ];

        foreach ($orders as $order) {
            Shopware()->Db()->insert('s_order', $order);
            array_push($this->orderIds, Shopware()->Db()->lastInsertId());
        }

        $orderDetails = [
            [
                'orderID' => $this->orderIds[0],
                'articleID' => $this->articleId,
                'articleordernumber' => $this->orderNumber,
                'price' => 1000,
                'quantity' => 1,
                'modus' => 0,
                'taxID' => 1,
                'tax_rate' => 19,
            ],
            [
                'orderID' => $this->orderIds[1],
                'articleID' => $this->articleId,
                'articleordernumber' => $this->orderNumber,
                'price' => 1000,
                'quantity' => 1,
                'modus' => 0,
                'taxID' => 1,
                'tax_rate' => 19,
            ],
        ];
        foreach ($orderDetails as $detail) {
            Shopware()->Db()->insert('s_order_details', $detail);
        }

        $userBillingAddress = [
            'company' => 'PHPUNIT',
            'salutation' => 'mr',
            'countryID' => 2,
            'stateID' => 3,
        ];

        $orderBillingAddresses = [
            [
                'userID' => $this->userId,
                'orderID' => $this->orderIds[0],
                'company' => $userBillingAddress['company'],
                'salutation' => $userBillingAddress['salutation'],
                'customernumber' => $this->customerNumber,
                'countryID' => $userBillingAddress['countryID'],
                'stateID' => $userBillingAddress['stateID'],
            ],
            [
                'userID' => $this->userId,
                'orderID' => $this->orderIds[1],
                'company' => $userBillingAddress['company'],
                'salutation' => $userBillingAddress['salutation'],
                'customernumber' => $this->customerNumber,
                'countryID' => $userBillingAddress['countryID'],
                'stateID' => $userBillingAddress['stateID'],
            ],
            [
                'userID' => $this->userId,
                'orderID' => $this->orderIds[2],
                'company' => $userBillingAddress['company'],
                'salutation' => $userBillingAddress['salutation'],
                'customernumber' => $this->customerNumber,
                'countryID' => $userBillingAddress['countryID'],
                'stateID' => $userBillingAddress['stateID'],
            ],
        ];
        foreach ($orderBillingAddresses as $address) {
            Shopware()->Db()->insert('s_order_billingaddress', $address);
        }
    }

    private function createVisitors()
    {
        $visitors = [
            [
                'shopID' => 1,
                'datum' => '2013-06-15',
                'pageimpressions' => 500,
                'uniquevisits' => 20,
            ],
            [
                'shopID' => 1,
                'datum' => '2013-06-01',
                'pageimpressions' => 300,
                'uniquevisits' => 10,
            ],
        ];
        foreach ($visitors as $visitor) {
            Shopware()->Db()->insert('s_statistics_visitors', $visitor);
        }
    }

    private function createImpressions()
    {
        Shopware()->Db()->insert(
            's_statistics_article_impression',
            [
                'articleId' => $this->articleId,
                'shopId' => 1,
                'date' => '2013-06-15',
                'impressions' => 10,
            ]
        );
    }

    private function createSearchTerms()
    {
        Shopware()->Db()->insert(
            's_statistics_search',
            [
                'datum' => '2013-06-15 10:11:12',
                'searchterm' => 'phpunit search term',
                'results' => 10,
            ]
        );
    }

    private function createReferrer()
    {
        Shopware()->Db()->insert(
            's_statistics_referer',
            [
                'datum' => '2013-06-15',
                'referer' => 'http://www.google.de/?q=phpunit',
            ]
        );
    }

    private function removeDemoData()
    {
        if ($this->userId) {
            Shopware()->Db()->delete('s_user', 'id = ' . $this->userId);
            Shopware()->Db()->delete('s_user_addresses', 'user_id = ' . $this->userId);
            Shopware()->Db()->delete('s_user_billingaddress', 'userID = ' . $this->userId);
            Shopware()->Db()->delete('s_order', 'userID = ' . $this->userId);
            Shopware()->Db()->delete('s_order_billingaddress', 'userID = ' . $this->userId);
        }

        if ($this->articleDetailId) {
            Shopware()->Db()->delete('s_articles_details', 'id = ' . $this->articleDetailId);
        }

        if ($this->articleId) {
            Shopware()->Db()->delete('s_articles', 'id = ' . $this->articleId);
            Shopware()->Db()->delete('s_statistics_article_impression', 'articleId = ' . $this->articleId);
            Shopware()->Db()->delete('s_order_details', 'articleID = ' . $this->articleId);
        }

        if ($this->categoryId) {
            if ($this->articleId) {
                Shopware()->Db()->delete('s_articles_categories_ro', 'articleID = ' . $this->articleId);
            }
            Shopware()->Db()->delete('s_categories', 'id = ' . $this->categoryId);
        }

        Shopware()->Db()->delete('s_statistics_visitors', "shopID = 1 AND datum = '2013-06-01' OR datum = '2013-06-15'");
        Shopware()->Db()->delete('s_statistics_search', "searchterm = 'phpunit search term'");
        Shopware()->Db()->delete('s_statistics_referer', "referer = 'http://www.google.de/?q=phpunit'");
    }

    private function getSearchTermFromReferrerUrl($url)
    {
        preg_match_all(
            '#[?&]([qp]|query|highlight|encquery|url|field-keywords|as_q|sucheall|satitle|KW)=([^&\$]+)#',
                utf8_encode($url) . '&',
            $matches
        );
        if (empty($matches[0])) {
            return '';
        }

        $ref = $matches[2][0];
        $ref = html_entity_decode(rawurldecode(strtolower($ref)));
        $ref = str_replace('+', ' ', $ref);
        $ref = trim(preg_replace('/\s\s+/', ' ', $ref));

        return $ref;
    }
}
