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

use Shopware\Models\Voucher\Voucher;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Models_VoucherTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Shopware\Models\User\Repository
     */
    protected $repo;

    /**
     * Voucher dummy data
     *
     * @var array
     */
    private $testData = [
        'description' => 'description',
        'minimumCharge' => '20',
        'modus' => '1',
        'numOrder' => '0',
        'voucherCode' => '',
        'numberOfUnits' => '50',
        'orderCode' => '65168phpunit',
        'percental' => '0',
        'taxConfig' => 'none',
        'shippingFree' => 0,
        'customerGroup' => 0,
        'restrictArticles' => '',
        'strict' => 0,
        'shopId' => 0,
        'bindToSupplier' => 0,
        'value' => '10',
    ];

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->em = Shopware()->Models();
        $this->repo = Shopware()->Models()->getRepository('Shopware\Models\Voucher\Voucher');
    }

    /**
     * Tear down
     */
    protected function tearDown()
    {
        $voucher = $this->repo->findOneBy(['description' => 'description']);

        if (!empty($voucher)) {
            $this->em->remove($voucher);
            $this->em->flush();
        }
        parent::tearDown();
    }

    /**
     * Test case getter and setter
     */
    public function testGetterAndSetter()
    {
        $voucher = new Voucher();

        foreach ($this->testData as $field => $value) {
            $setMethod = 'set' . ucfirst($field);
            $getMethod = 'get' . ucfirst($field);

            $voucher->$setMethod($value);
            $this->assertEquals($voucher->$getMethod(), $value);
        }
    }

    /**
     * Test case from array
     */
    public function testFromArrayWorks()
    {
        $voucher = new Voucher();
        $voucher->fromArray($this->testData);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($voucher->$getMethod(), $value);
        }
    }

    /**
     * Test case voucher should be persisted
     */
    public function testVoucherShouldBePersisted()
    {
        $voucher = new Voucher();
        $voucher->fromArray($this->testData);

        $this->em->persist($voucher);
        $this->em->flush();

        $voucherId = $voucher->getId();

        // remove form from entity manager
        $this->em->detach($voucher);
        unset($voucher);

        $voucher = $this->repo->find($voucherId);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($voucher->$getMethod(), $value);
        }
    }
}
