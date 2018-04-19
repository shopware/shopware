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
class Shopware_Tests_Controllers_Backend_SupplierTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * Supplier dummy data
     *
     * @var array
     */
    private $supplierData = [
        'name' => '__supplierTest',
        'link' => 'www.example.com',
        'description' => 'Test Supplier added by <a href="http://www.phpunit.de">unit test.</a>',
        'image' => 'media/image/testImage.jpg',
    ];

    /**
     * Standard set up for every test - just disable auth
     */
    public function setUp()
    {
        parent::setUp();

        // disable auth and acl
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();
    }

    /**
     * Test Method to test
     *
     * a) can this action be dispatched
     * b) is the answer encapsulated in a JSON header
     */
    public function testGetSuppliers()
    {
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        /* @var Enlight_Controller_Response_ResponseTestCase */
        $this->dispatch('backend/supplier/getSuppliers');
        $this->assertTrue($this->View()->success);

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('total', $jsonBody);
        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
    }

    /**
     * Method to test: adding a supplier to the db
     * This method has to be called before the delete test
     *
     * @return array
     */
    public function testAddSupplier()
    {
        $this->Request()->setMethod('POST')->setPost($this->supplierData);
        $this->dispatch('backend/supplier/createSupplier');
        $this->assertTrue($this->View()->success);

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);

        return $jsonBody['data'];
    }

    /**
     * @depends testAddSupplier
     *
     * @param $lastSupplier
     *
     * @return array
     */
    public function testUpdateSupplier($lastSupplier)
    {
        foreach ($lastSupplier as $key => $value) {
            if (!is_null($value)) {
                $supplier[$key] = $value;
            }
        }
        $supplier['name'] = '___testSupplier_UPDATE';

        $this->Request()->setMethod('POST')->setPost($supplier);
        $this->dispatch('backend/supplier/updateSupplier');
        $this->assertTrue($this->View()->success);

        $jsonBody = $this->View()->getAssign();

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);

        return $jsonBody['data'];
    }

    /**
     * Tests if the supplier can be removed from the database
     * The lastId is the id from the last add test
     *
     * @depends testUpdateSupplier
     *
     * @param array $lastSupplier
     */
    public function testDeleteSupplier(array $lastSupplier)
    {
        $this->Request()->setMethod('POST')->setPost(['id' => $lastSupplier['id']]);
        $this->dispatch('backend/supplier/deleteSupplier');
        $this->assertTrue($this->View()->success);
    }
}
