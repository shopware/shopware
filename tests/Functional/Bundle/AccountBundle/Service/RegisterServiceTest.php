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

namespace Shopware\Tests\Functional\Bundle\AccountBundle\Service;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\AccountBundle\Service\RegisterServiceInterface;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Service\ContextFactoryInterface;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopScope;
use Shopware\Shop\Struct\Shop;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
use Shopware\Models\Country\State;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;

/**
 * Class AddressServiceTest
 */
class RegisterServiceTest extends \Enlight_Components_Test_TestCase
{
    /**
     * @var RegisterServiceInterface
     */
    protected static $registerService;

    /**
     * @var ModelManager
     */
    protected static $modelManager;

    /**
     * @var Connection
     */
    protected static $connection;

    /**
     * @var \Shopware\Storefront\Context\StorefrontContextServiceInterface
     */
    protected static $contextService;

    /**
     * @var array
     */
    protected static $_cleanup = [];

    /**
     * @var ContextFactoryInterface
     */
    private static $contextFactory;

    /**
     * Set up fixtures
     */
    public static function setUpBeforeClass()
    {
        self::$registerService = Shopware()->Container()->get('shopware_account.register_service');
        self::$modelManager = Shopware()->Container()->get('models');
        self::$connection = Shopware()->Container()->get('dbal_connection');
        self::$contextService = Shopware()->Container()->get('storefront.context.service');
        self::$contextFactory = Shopware()->Container()->get('storefront.context.factory');

        self::$modelManager->clear();
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

    /**
     * @expectedException \Doctrine\ORM\ORMException
     * @expectedExceptionMessage The identifier id is missing for a query of Shopware\Models\Shop\Shop
     */
    public function testRegisterWithEmptyData()
    {
        $shop = new Shop();
        $customer = new Customer();
        $billing = new Address();

        self::$registerService->register($shop, $customer, $billing);
    }

    /**
     * @expectedException \Doctrine\ORM\ORMException
     * @expectedExceptionMessage The identifier id is missing for a query of Shopware\Models\Shop\Shop
     */
    public function testRegisterWithEmptyShop()
    {
        $shop = new Shop();

        $customer = new Customer();
        $customer->fromArray($this->getCustomerDemoData());

        $billing = new Address();
        $billing->fromArray($this->getBillingDemoData());

        self::$registerService->register($shop, $customer, $billing);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testRegisterWithEmptyCustomer()
    {
        $shop = $this->getShop();

        $customer = new Customer();

        $billing = new Address();
        $billing->fromArray($this->getBillingDemoData());

        self::$registerService->register($shop, $customer, $billing);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testRegisterWithEmptyAddress()
    {
        $shop = $this->getShop();

        $customer = new Customer();
        $customer->fromArray($this->getCustomerDemoData());

        $billing = new Address();

        self::$registerService->register($shop, $customer, $billing);
    }

    public function testRegister()
    {
        $demoData = $this->getCustomerDemoData();
        $billingDemoData = $this->getBillingDemoData();

        $shop = $this->getShop();

        $customer = new Customer();
        $customer->fromArray($demoData);

        $billing = new Address();
        $billing->fromArray($billingDemoData);

        self::$registerService->register($shop, $customer, $billing);

        $this->assertGreaterThan(0, $customer->getId());

        self::$modelManager->refresh($customer);

        self::$_cleanup[Customer::class][] = $customer->getId();
        self::$_cleanup[Address::class][] = $billing->getId();

        $this->assertCustomer($demoData, $customer);

        // assert data sync
        $this->assertAddress($billingDemoData, $customer);
        $this->assertAddress($billingDemoData, $customer, true);

        return $customer->getId();
    }

    public function testRegisterWithDifferentShipping()
    {
        $demoData = $this->getCustomerDemoData(true);
        $billingDemoData = $this->getBillingDemoData();
        $shippingDemoData = $this->getShippingDemoData();

        $shop = $this->getShop();

        $customer = new Customer();
        $customer->fromArray($demoData);

        $billing = new Address();
        $billing->fromArray($billingDemoData);

        $shipping = new Address();
        $shipping->fromArray($shippingDemoData);

        self::$registerService->register($shop, $customer, $billing, $shipping);

        $this->assertGreaterThan(0, $customer->getId());

        self::$modelManager->refresh($customer);

        self::$_cleanup[Customer::class][] = $customer->getId();
        self::$_cleanup[Address::class][] = $billing->getId();
        self::$_cleanup[Address::class][] = $shipping->getId();

        $this->assertCustomer($demoData, $customer);

        // assert data sync
        $this->assertAddress($billingDemoData, $customer);
        $this->assertAddress($shippingDemoData, $customer, true);
    }

    /**
     * @depends testRegister
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testRegisterWithExistingEmail()
    {
        $demoData = $this->getCustomerDemoData();

        $shop = $this->getShop();

        $customer = new Customer();
        $customer->fromArray($demoData);

        $billing = new Address();
        $billing->fromArray($this->getBillingDemoData());

        self::$registerService->register($shop, $customer, $billing);
    }

    /**
     * Helper method for creating a valid customer
     *
     * @param bool $randomEmail
     *
     * @return array
     */
    private function getCustomerDemoData($randomEmail = false)
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

    private function getBillingDemoData()
    {
        $country = $this->createCountry();

        $data = [
            'salutation' => 'mr',
            'firstname' => 'Sherman',
            'lastname' => 'Horton',
            'street' => '1117 Washington Street',
            'zipcode' => '78372',
            'city' => 'Orange Grove',
            'country' => $country,
            'state' => $this->createState($country),
        ];

        return $data;
    }

    private function getShippingDemoData()
    {
        $data = [
            'salutation' => 'mr',
            'firstname' => 'Nathaniel',
            'lastname' => 'Fajardo',
            'street' => '3844 Euclid Avenue',
            'zipcode' => '93101',
            'city' => 'Santa Barbara',
            'country' => $this->createCountry(),
        ];

        return $data;
    }

    /**
     * @return Country
     */
    private function createCountry()
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
     * @return \Shopware\Shop\Struct\Shop
     */
    private function getShop()
    {
        return self::$contextFactory->create(
            new ShopScope(1),
            new CustomerScope(null),
            new CheckoutScope()
        )->getShop();
    }

    /**
     * @param Country $country
     *
     * @return State
     */
    private function createState(Country $country)
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

    /**
     * @param array    $demoData
     * @param Customer $customer
     */
    private function assertCustomer(array $demoData, Customer $customer)
    {
        $this->assertEquals($demoData['salutation'], $customer->getSalutation());
        $this->assertEquals($demoData['firstname'], $customer->getFirstname());
        $this->assertEquals($demoData['lastname'], $customer->getLastname());
        $this->assertEquals($demoData['email'], $customer->getEmail());
        $this->assertEquals('EK', $customer->getGroup()->getKey());
        $this->assertNotEmpty($customer->getPassword());

        $this->assertNotNull($customer->getBilling());
        $this->assertNotNull($customer->getShipping());
    }

    /**
     * @param array    $demoData
     * @param Customer $customer
     * @param bool     $shipping
     */
    private function assertAddress(array $demoData, Customer $customer, $shipping = false)
    {
        $legacyAddress = $shipping ? $customer->getShipping() : $customer->getBilling();
        $address = $shipping ? $customer->getDefaultShippingAddress() : $customer->getDefaultBillingAddress();

        $this->assertEquals($demoData['firstname'], $legacyAddress->getFirstName());
        $this->assertEquals($demoData['firstname'], $address->getFirstname());

        $this->assertEquals($demoData['lastname'], $legacyAddress->getLastName());
        $this->assertEquals($demoData['lastname'], $address->getLastname());

        $this->assertEquals($demoData['country']->getId(), $legacyAddress->getCountryId());
        $this->assertEquals($demoData['country']->getId(), $address->getCountry()->getId());

        if (!empty($demoData['state'])) {
            $this->assertEquals($demoData['state']->getId(), $legacyAddress->getStateId());
            $this->assertEquals($demoData['state']->getId(), $address->getState()->getId());
        }
    }
}
