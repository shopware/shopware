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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Address;

/**
 * Class AddressTest
 */
class AddressTest extends TestCase
{
    /**
     * @var Address
     */
    protected $resource;

    /**
     * @return Address
     */
    public function createResource()
    {
        return new Address();
    }

    public function testCreateShouldBeSuccessful()
    {
        $testData = [
            'customer' => 2,
            'salutation' => 'mr',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'street' => 'Musterstr. 55',
            'zipcode' => '12345',
            'city' => 'Musterhausen',
            'country' => 2,
        ];

        $address = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Customer\Address', $address);
        $this->assertGreaterThan(0, $address->getId());

        $this->assertEquals($testData['country'], $address->getCountry()->getId());
        $this->assertEquals($testData['firstname'], $address->getFirstname());
        $this->assertEquals($testData['lastname'], $address->getLastname());

        return $address->getId();
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeSuccessful($id)
    {
        $address = $this->resource->getOne($id);
        $this->assertGreaterThan(0, $address['id']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeSuccessful()
    {
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateShouldBeSuccessful($id)
    {
        $testData = [
            'lastname' => uniqid(rand()) . ' new lastname',
            'zipcode' => '98765',
        ];

        $address = $this->resource->update($id, $testData);

        $this->assertInstanceOf('\Shopware\Models\Customer\Address', $address);
        $this->assertEquals($id, $address->getId());

        $this->assertEquals($address->getLastname(), $testData['lastname']);
        $this->assertEquals($address->getZipcode(), $testData['zipcode']);

        return $id;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testNewAddressShouldNotBeDefault($id)
    {
        $newAddressId = $this->testCreateShouldBeSuccessful();
        $address = $this->resource->getOne($newAddressId);
        $customer = Shopware()->Models()->find('Shopware\Models\Customer\Customer', $address['customer']['id']);

        $this->assertNotEquals($newAddressId, $customer->getDefaultBillingAddress()->getId());

        return $newAddressId;
    }

    /**
     * @depends testNewAddressShouldNotBeDefault
     */
    public function testMakeNewAddressTheDefault($id)
    {
        $testData = [
            '__options_set_default_billing_address' => 1,
            '__options_set_default_shipping_address' => 1,
        ];

        $address = $this->resource->update($id, $testData);

        $this->assertEquals($id, $address->getCustomer()->getDefaultBillingAddress()->getId());
        $this->assertEquals($id, $address->getCustomer()->getDefaultShippingAddress()->getId());

        return $id;
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testUpdateWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->update(9999999, []);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testUpdateWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->update('', []);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testDeleteShouldBeSuccessfulAfterOtherDefaultAddress($id)
    {
        $address = $this->resource->delete($id);

        $this->assertInstanceOf('\Shopware\Models\Customer\Address', $address);
        $this->assertEquals(null, $address->getId());
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testDeleteWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->delete(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testDeleteWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->delete('');
    }
}
