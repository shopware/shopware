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

namespace Shopware\Tests\Functional\Bundle\AccountBundle\Controller;

use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopScope;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
use Shopware\Models\Country\State;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @group disable
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class AddressTest extends \Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var ModelManager
     */
    private static $modelManager;

    /**
     * @var array
     */
    private static $_cleanup = [];

    /**
     * @var string
     */
    private static $loginEmail;

    /**
     * @var string
     */
    private static $loginPassword;

    /**
     * @var Customer
     */
    private static $customer;

    /**
     * Create one customer to be used for these tests
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$modelManager = Shopware()->Container()->get('models');
        self::$modelManager->clear();

        // Register customer
        $demoData = self::getCustomerDemoData(true);
        $billingDemoData = self::getBillingDemoData();
        $shippingDemoData = self::getShippingDemoData();

        $shop = Shopware()->Container()->get('storefront.context.factory')->create(
            new ShopScope(1),
            new CustomerScope(null),
            new CheckoutScope()
        )->getShop();

        $customer = new Customer();
        $customer->fromArray($demoData);

        $billing = new Address();
        $billing->fromArray($billingDemoData);

        $shipping = new Address();
        $shipping->fromArray($shippingDemoData);

        $registerService = Shopware()->Container()->get('shopware_account.register_service');
        $registerService->register($shop, $customer, $billing, $shipping);

        self::$loginEmail = $demoData['email'];
        self::$loginPassword = $demoData['password'];
        self::$customer = $customer;

        self::$_cleanup[Customer::class][] = $customer->getId();
    }

    /**
     * Clean up created entities and database entries
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        foreach (self::$_cleanup as $entityName => $ids) {
            foreach ($ids as $id) {
                self::$modelManager->remove(self::$modelManager->find($entityName, $id));
            }
        }

        self::$modelManager->flush();
        self::$modelManager->clear();

        Shopware()->Container()->reset('router');
    }

    public function testList()
    {
        $this->ensureLogin();
        $crawler = $this->doRequest('GET', '/address/');

        $this->assertEquals(3, $crawler->filter('.address--item-content')->count());
        $this->assertGreaterThan(0, $crawler->filter('html:contains("Standard-Rechnungsadresse")')->count());
        $this->assertGreaterThan(0, $crawler->filter('html:contains("Standard-Lieferadresse")')->count());
    }

    /**
     * @return int
     */
    public function testCreation()
    {
        $this->ensureLogin();
        $crawler = $this->doRequest(
            'POST',
            '/address/create/',
            [
                'address' => [
                    'salutation' => 'mr',
                    'firstname' => 'Luis',
                    'lastname' => 'King',
                    'street' => 'Fasanenstrasse 99',
                    'zipcode' => '79268',
                    'city' => 'Bötzingen',
                    'country' => 2,
                ],
            ]
        );

        $this->assertEquals(1, $crawler->filter('html:contains("Die Adresse wurde erfolgreich erstellt")')->count());
        $this->assertGreaterThan(0, $crawler->filter('.address--item-content:contains("Fasanenstrasse 99")')->count());
        $this->assertEquals(4, $crawler->filter('.address--item-content')->count());

        return (int) $crawler->filter('.address--item-content:contains("Fasanenstrasse 99")')->filter('input[name=addressId]')->attr('value');
    }

    /**
     * @param $addressId
     * @depends testCreation
     */
    public function testEditPage($addressId)
    {
        $this->ensureLogin();

        // edit page
        $crawler = $this->doRequest('GET', '/address/edit/id/' . $addressId);
        $this->assertEquals('Fasanenstrasse 99', $crawler->filter('input[name="address[street]"]')->attr('value'));
    }

    /**
     * @param $addressId
     * @depends testCreation
     */
    public function testEdit($addressId)
    {
        $this->ensureLogin();

        // edit operation
        $crawler = $this->doRequest(
            'POST',
            '/address/edit/id/' . $addressId,
            [
                'address' => [
                    'salutation' => 'mr',
                    'firstname' => 'Joe',
                    'lastname' => 'Doe',
                    'street' => 'Fasanenstrasse 99',
                    'zipcode' => '79268',
                    'city' => 'Bötzingen',
                    'country' => 2,
                ],
            ]
        );

        $this->assertEquals(1, $crawler->filter('html:contains("Die Adresse wurde erfolgreich gespeichert")')->count());
        $this->assertGreaterThan(0, $crawler->filter('.address--item-content:contains("Joe Doe")')->count());
        $this->assertGreaterThan(0, $crawler->filter('.address--item-content:contains("Fasanenstrasse 99")')->count());
        $this->assertEquals(4, $crawler->filter('.address--item-content')->count());
    }

    /**
     * @depends testCreation
     *
     * @param int $addressId
     */
    public function testDeletion($addressId)
    {
        $this->ensureLogin();

        // delete confirm page
        $crawler = $this->doRequest('GET', '/address/delete/id/' . $addressId);
        $this->assertEquals(1, $crawler->filter('html:contains("Fasanenstrasse 99")')->count());

        // delete operation
        $crawler = $this->doRequest('POST', '/address/delete/id/' . $addressId, ['id' => $addressId]);
        $this->assertEquals(1, $crawler->filter('html:contains("Die Adresse wurde erfolgreich gelöscht")')->count());
        $this->assertEquals(3, $crawler->filter('.address--item-content')->count());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The address is defined as default billing or shipping address and cannot be removed.
     */
    public function testDeletionOfDefaultAddressesShouldFail()
    {
        $this->ensureLogin();
        $addressId = self::$customer->getDefaultBillingAddress()->getId();

        $this->doRequest('POST', '/address/delete/id/' . $addressId . '/', ['id' => $addressId]);
    }

    /**
     * @depends testDeletionOfDefaultAddressesShouldFail
     */
    public function testVerifyAddressDeletionOfDefaultAddressesShouldFail()
    {
        $this->ensureLogin();

        $crawler = $this->doRequest('GET', '/address/');

        $this->assertEquals(3, $crawler->filter('.address--item-content')->count());
    }

    public function testChangeOfBillingAddressReflectsInAccount()
    {
        $this->ensureLogin();

        // crawl original data
        $crawler = $this->doRequest('GET', '/account');
        $originalText = trim($crawler->filter('.account--billing .panel--body p')->last()->text());
        $addressId = (int) $crawler->filter('.account--billing .panel--actions a:contains("oder andere Adresse wählen")')->attr('data-id');

        $this->assertGreaterThan(0, $addressId);

        // edit the entry
        $expectedText = 'Herr
Shop ManMusterstr. 5555555 Musterhausen
Nordrhein-WestfalenDeutschland';

        $this->doRequest(
            'POST',
            '/address/edit/id/' . $addressId,
            [
                'address' => [
                    'salutation' => 'mr',
                    'company' => 'Muster GmbH',
                    'firstname' => 'Shop',
                    'lastname' => 'Man',
                    'street' => 'Musterstr. 55',
                    'zipcode' => '55555',
                    'city' => 'Musterhausen',
                    'country' => 2,
                    'state' => 3,
                ],
            ]
        );

        // verify the changes
        $crawler = $this->doRequest('GET', '/account');
        $currentText = trim($crawler->filter('.account--billing .panel--body p')->last()->text());

        $this->assertNotEquals($originalText, $currentText);
        $this->assertEquals($expectedText, $currentText);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $data
     *
     * @return Crawler
     */
    private function doRequest($method, $url, $data = [])
    {
        $this->reset();
        $e = Shopware()->Container()->get('events');
        $e->addSubscriber(Shopware()->Container()->get('shopware.components.seo_template_subscriber'));

        $this->Request()->setMethod($method);

        if ($method === 'POST') {
            $this->Request()->setPost($data);
        }

        $this->dispatch($url);

        if ($this->Response()->isRedirect()) {
            $parts = parse_url($this->Response()->getHeaders()[0]['value']);
            $followUrl = $parts['path'];

            return $this->doRequest('GET', $followUrl);
        }

        return new Crawler($this->Response()->getBody());
    }

    /**
     * Log-in into account, needed for every test
     */
    private function ensureLogin()
    {
        $this->doRequest('POST', '/account/login', ['email' => self::$loginEmail, 'password' => self::$loginPassword]);
    }

    /**
     * Helper method for creating a valid customer
     *
     * @param bool $randomEmail
     *
     * @return array
     */
    private static function getCustomerDemoData($randomEmail = false)
    {
        $emailPrefix = $randomEmail ? uniqid(rand()) : '';

        $data = [
            'salutation' => 'mr',
            'firstname' => 'Albert',
            'lastname' => 'McTaggart',
            'email' => $emailPrefix . 'albert.mctaggart@shopware.test',
            'password' => uniqid(rand()),
        ];

        return $data;
    }

    private static function getBillingDemoData()
    {
        $country = self::createCountry();

        $data = [
            'salutation' => 'mr',
            'firstname' => 'Sherman',
            'lastname' => 'Horton',
            'street' => '1117 Washington Street',
            'zipcode' => '78372',
            'city' => 'Orange Grove',
            'country' => $country,
            'state' => self::createState($country),
        ];

        return $data;
    }

    private static function getShippingDemoData()
    {
        $data = [
            'salutation' => 'mr',
            'firstname' => 'Nathaniel',
            'lastname' => 'Fajardo',
            'street' => '3844 Euclid Avenue',
            'zipcode' => '93101',
            'city' => 'Santa Barbara',
            'country' => self::createCountry(),
        ];

        return $data;
    }

    /**
     * @return Country
     */
    private static function createCountry()
    {
        $country = new Country();

        $country->setName('ShopwareLand ' . uniqid(rand()));
        $country->setActive(true);
        $country->setDisplayStateInRegistration(1);
        $country->setForceStateInRegistration(0);

        self::$modelManager->persist($country);
        self::$modelManager->flush($country);

        self::$_cleanup[Country::class][] = $country->getId();

        return self::$modelManager->merge($country);
    }

    /**
     * @param Country $country
     *
     * @return State
     */
    private static function createState(Country $country)
    {
        $state = new State();

        $state->setName('Shopware CountryState ' . uniqid(rand()));
        $state->setActive(1);
        $state->setCountry($country);
        $state->setShortCode(uniqid(rand()));

        self::$modelManager->persist($state);
        self::$modelManager->flush($state);

        self::$_cleanup[State::class][] = $state->getId();

        return self::$modelManager->merge($state);
    }
}
