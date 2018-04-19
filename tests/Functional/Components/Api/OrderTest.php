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

use Shopware\Components\Api\Resource\Order;
use Shopware\Components\Api\Resource\Resource;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class OrderTest extends TestCase
{
    /**
     * @var Order
     */
    protected $resource;

    /**
     * @var array
     */
    private $order;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->order = Shopware()->Db()->fetchRow('SELECT * FROM  `s_order` LIMIT 1');
    }

    /**
     * @return Order
     */
    public function createResource()
    {
        return new Order();
    }

    public function testGetOneShouldBeSuccessful()
    {
        $order = $this->resource->getOne($this->order['id']);
        $this->assertEquals($this->order['id'], $order['id']);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testGetOneByNumberWithInvalidNumberShouldThrowNotFoundException()
    {
        $this->resource->getOneByNumber(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testGetOneByNumberWithMissinNumberShouldThrowParameterMissingException()
    {
        $this->resource->getOneByNumber('');
    }

    public function testGetOneByNumberShouldBeSuccessful()
    {
        $order = $this->resource->getOneByNumber($this->order['ordernumber']);
        $this->assertEquals($this->order['ordernumber'], $order['number']);
    }

    public function testGetOneShouldBeAbleToReturnObject()
    {
        $this->resource->setResultMode(Resource::HYDRATE_OBJECT);
        $order = $this->resource->getOne($this->order['id']);

        $this->assertInstanceOf('\Shopware\Models\Order\Order', $order);
        $this->assertEquals($this->order['id'], $order->getId());
    }

    public function testGetListShouldBeSuccessful()
    {
        $result = $this->resource->getList();

        $this->assertInternalType('array', $result);

        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThanOrEqual(1, $result['total']);

        $this->assertArrayHasKey('data', $result);
        $this->assertInternalType('array', $result['data']);

        $this->assertGreaterThanOrEqual(1, count($result['data']));

        $firstOrder = $result['data'][0];

        $expectedKeys = [
            'id',
            'number',
            'customerId',
            'paymentId',
            'dispatchId',
            'partnerId',
            'shopId',
            'invoiceAmount',
            'invoiceAmountNet',
            'invoiceShipping',
            'invoiceShippingNet',
            'orderTime',
            'transactionId',
            'comment',
            'customerComment',
            'internalComment',
            'net',
            'taxFree',
            'temporaryId',
            'referer',
            'clearedDate',
            'trackingCode',
            'languageIso',
            'currency',
            'currencyFactor',
            'remoteAddress',
            'deviceType',
            'customer',
            'paymentStatusId',
            'orderStatusId',
        ];

        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $firstOrder);
        }

        $this->assertInternalType('array', $firstOrder['customer']);
        $this->assertArrayHasKey('id', $firstOrder['customer']);
        $this->assertArrayHasKey('email', $firstOrder['customer']);
    }

    public function testGetListShouldBeAbleToReturnObjects()
    {
        $this->resource->setResultMode(Resource::HYDRATE_OBJECT);
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);

        $this->assertInstanceOf('\Shopware\Models\Order\Order', $result['data'][0]);
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

    public function testUpdateOrderPositionStatusShouldBeSuccessful()
    {
        // Get existing order
        $this->resource->setResultMode(Resource::HYDRATE_ARRAY);
        $order = $this->resource->getOne($this->order['id']);

        // Update the order details of that order
        $updateArray = [];
        foreach ($order['details'] as $detail) {
            $updateArray['details'][$detail['id']] = ['id' => $detail['id'], 'status' => rand(0, 3), 'shipped' => 1];
        }
        $this->resource->update($this->order['id'], $updateArray);

        // Reload the order and check the result
        $this->resource->setResultMode(Resource::HYDRATE_ARRAY);
        $order = $this->resource->getOne($this->order['id']);
        foreach ($order['details'] as $detail) {
            $currentId = $detail['id'];

            $this->assertEquals($updateArray['details'][$currentId]['status'], $detail['statusId']);
            $this->assertEquals($updateArray['details'][$currentId]['shipped'], $detail['shipped']);
        }
    }
}
