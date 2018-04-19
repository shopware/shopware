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
use Shopware\Storefront\Context\StorefrontContextServiceInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;

/**
 * Class AddressServiceTest
 */
class AddressServiceTest extends \Enlight_Components_Test_TestCase
{
    /**
     * @var \Shopware\Bundle\AccountBundle\Service\AddressServiceInterface
     */
    protected static $addressService;

    /**
     * @var ModelManager
     */
    protected static $modelManager;

    /**
     * @var Connection
     */
    protected static $connection;

    /**
     * @var StorefrontContextServiceInterface
     */
    protected static $contextService;

    /**
     * @var RegisterServiceInterface
     */
    protected static $registerService;

    /**
     * @var array
     */
    protected static $_cleanup = [];

    /**
     * Set up fixtures
     */
    public static function setUpBeforeClass()
    {
        self::$addressService = Shopware()->Container()->get('shopware_account.address_service');
        self::$modelManager = Shopware()->Container()->get('models');
        self::$connection = Shopware()->Container()->get('dbal_connection');
        self::$contextService = Shopware()->Container()->get('storefront.context.service');
        self::$registerService = Shopware()->Container()->get('shopware_account.register_service');

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
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testCreateWithEmptyData()
    {
        $address = new Address();
        $customer = new Customer();

        self::$addressService->create($address, $customer);
    }

    /**
     * @expectedException \Doctrine\ORM\ORMInvalidArgumentException
     */
    public function testCreateWithEmptyCustomer()
    {
        $address = new Address();
        $address->setSalutation('mr');
        $address->setFirstname('Lars');
        $address->setLastname('Larsson');
        $address->setStreet('Mayerstreet 22');
        $address->setZipcode('4498');
        $address->setCity('Oslo');
        $address->setCountry($this->createCountry());

        $customer = new Customer();

        self::$addressService->create($address, $customer);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testCreateWithEmptyAddress()
    {
        $address = new Address();
        $customer = $this->createCustomer();

        self::$addressService->create($address, $customer);
    }

    public function testCreateAddress()
    {
        $addressData = [
            'salutation' => 'mr',
            'firstname' => 'Lars',
            'lastname' => 'Larsson',
            'street' => 'Mayerstreet 22',
            'zipcode' => '4498',
            'city' => 'Oslo',
            'country' => $this->createCountry(),
        ];

        $address = new Address();
        $address->fromArray($addressData);

        $customer = $this->createCustomer();

        self::$addressService->create($address, $customer);

        $this->assertInstanceOf(Address::class, $address);
        $this->assertNotNull($address->getId());

        foreach ($addressData as $key => $value) {
            $getter = 'get' . ucfirst($key);
            $this->assertEquals($value, $address->$getter());
        }

        return $address->getId();
    }

    /**
     * @depends testCreateAddress
     */
    public function testSetDefaultBilling($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        self::$addressService->setDefaultBillingAddress($address);

        $billing = $address->getCustomer()->getDefaultBillingAddress();

        $this->assertEquals($address->getId(), $billing->getId());
        $this->assertEquals($address->getFirstname(), $billing->getFirstname());
        $this->assertEquals($address->getLastname(), $billing->getLastname());

        return $addressId;
    }

    /**
     * @depends testSetDefaultBilling
     */
    public function testSetDefaultBillingLegacySync($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        $billing = $address->getCustomer()->getBilling();

        $this->assertEquals($address->getCompany(), $billing->getCompany());
        $this->assertEquals($address->getDepartment(), $billing->getDepartment());
        $this->assertEquals($address->getDepartment(), $billing->getDepartment());
        $this->assertEquals($address->getSalutation(), $billing->getSalutation());
        $this->assertEquals($address->getFirstname(), $billing->getFirstName());
        $this->assertEquals($address->getLastname(), $billing->getLastName());
        $this->assertEquals($address->getStreet(), $billing->getStreet());
        $this->assertEquals($address->getZipcode(), $billing->getZipCode());
        $this->assertEquals($address->getCity(), $billing->getCity());
        $this->assertEquals($address->getPhone(), $billing->getPhone());
        $this->assertEquals($address->getCountry()->getId(), $billing->getCountryId());
        $this->assertEquals($address->getState() ? $address->getState()->getId() : null, $billing->getStateId());
        $this->assertEquals($address->getVatId(), $billing->getVatId());
        $this->assertEquals($address->getAdditionalAddressLine1(), $billing->getAdditionalAddressLine1());
        $this->assertEquals($address->getAdditionalAddressLine2(), $billing->getAdditionalAddressLine2());
    }

    /**
     * @depends testSetDefaultBilling
     */
    public function testUpdateBilling($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        $address->setFirstname('Zara');
        $address->setCity('Kopenhagen');

        self::$addressService->update($address);

        $this->testSetDefaultBillingLegacySync($addressId);

        return $addressId;
    }

    /**
     * @depends testCreateAddress
     */
    public function testSetDefaultShipping($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        self::$addressService->setDefaultShippingAddress($address);

        $shipping = $address->getCustomer()->getDefaultShippingAddress();

        $this->assertEquals($address->getId(), $shipping->getId());
        $this->assertEquals($address->getFirstname(), $shipping->getFirstname());
        $this->assertEquals($address->getLastname(), $shipping->getLastname());

        return $addressId;
    }

    /**
     * @depends testSetDefaultShipping
     */
    public function testSetDefaultShippingLegacySync($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        $shipping = $address->getCustomer()->getShipping();

        $this->assertEquals($address->getCompany(), $shipping->getCompany());
        $this->assertEquals($address->getDepartment(), $shipping->getDepartment());
        $this->assertEquals($address->getDepartment(), $shipping->getDepartment());
        $this->assertEquals($address->getSalutation(), $shipping->getSalutation());
        $this->assertEquals($address->getFirstname(), $shipping->getFirstName());
        $this->assertEquals($address->getLastname(), $shipping->getLastName());
        $this->assertEquals($address->getStreet(), $shipping->getStreet());
        $this->assertEquals($address->getZipcode(), $shipping->getZipCode());
        $this->assertEquals($address->getCity(), $shipping->getCity());
        $this->assertEquals($address->getCountry()->getId(), $shipping->getCountryId());
        $this->assertEquals($address->getState() ? $address->getState()->getId() : null, $shipping->getStateId());
        $this->assertEquals($address->getAdditionalAddressLine1(), $shipping->getAdditionalAddressLine1());
        $this->assertEquals($address->getAdditionalAddressLine2(), $shipping->getAdditionalAddressLine2());
    }

    /**
     * @depends testSetDefaultBilling
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The address is defined as default billing or shipping address and cannot be removed.
     */
    public function testDeleteDefaultAddressShouldFail($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        self::$addressService->delete($address);
    }

    /**
     * @param $addressId
     * @depends testSetDefaultBilling
     * @depends testDeleteDefaultAddressShouldFail
     */
    public function testDeleteNonDefaultAddress($addressId)
    {
        $address = self::$modelManager->find(Address::class, $addressId);

        $unusedAddressId = self::$connection->executeQuery('SELECT id FROM s_user_addresses WHERE user_id = ? AND id != ?', [$address->getCustomer()->getId(), $address->getId()])->fetch(\PDO::FETCH_COLUMN);
        $this->assertGreaterThan(0, $unusedAddressId, 'No unused address found.');

        $unusedAddress = self::$modelManager->find(Address::class, $unusedAddressId);
        $this->assertNotNull($unusedAddress, 'Unused address entity (' . $unusedAddressId . ') not found.');

        self::$addressService->setDefaultBillingAddress($unusedAddress);
        self::$addressService->setDefaultShippingAddress($unusedAddress);

        self::$addressService->delete($address);

        $this->assertNull($address->getId());
        $this->assertNotNull($unusedAddress->getId());
    }

    /**
     * @return Country
     */
    private function createCountry()
    {
        $country = new Country();

        $country->setName('ShopwareLand' . uniqid(rand(1, 999)));
        $country->setActive(true);
        $country->setDisplayStateInRegistration(0);
        $country->setForceStateInRegistration(0);

        self::$modelManager->persist($country);
        self::$modelManager->flush($country);

        self::$_cleanup[Country::class][] = $country->getId();

        return self::$modelManager->merge($country);
    }

    /**
     * @return Customer
     */
    private function createCustomer()
    {
        $customer = new Customer();

        $customer->setEmail(uniqid(rand()) . 'test@foo.bar');
        $customer->setActive(true);
        $customer->setLastLogin(date('Y-m-d', strtotime('-8 days')));
        $customer->setPassword(uniqid(rand()) . uniqid(rand()));

        $customer->setSalutation('mr');
        $customer->setFirstname('Max');
        $customer->setLastname('Mustermann');

        $billing = $this->createBillingEntity();
        $shipping = $this->createShippingEntity();

        $shop = self::$contextService->getShopContext()->getShop();

        self::$registerService->register($shop, $customer, $billing, $shipping);

        self::$_cleanup[Customer::class][] = $customer->getId();

        return $customer;
    }

    /**
     * @return Address
     */
    private function createBillingEntity()
    {
        $billing = new Address();

        $country = $this->createCountry();

        $billing->setSalutation('mr');
        $billing->setFirstname('Nathan');
        $billing->setLastname('Davis');
        $billing->setZipcode('92123');
        $billing->setCity('San Diego');
        $billing->setCountry($country);
        $billing->setStreet('4193 Pike Street');

        return $billing;
    }

    /**
     * @return Address
     */
    private function createShippingEntity()
    {
        $shipping = new Address();

        $country = $this->createCountry();

        $shipping->setSalutation('mr');
        $shipping->setFirstname('Michael');
        $shipping->setLastname('Crosby');
        $shipping->setZipcode('36542');
        $shipping->setCity('Gulf Shores');
        $shipping->setCountry($country);
        $shipping->setStreet('4267 Lonely Oak Drive');

        return $shipping;
    }
}
