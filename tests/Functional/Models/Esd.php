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

use Shopware\Models\Article\Esd;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Models_EsdTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var array
     */
    public $testData = [
        'file' => '../foobar.pdf',
        'hasSerials' => true,
        'notification' => true,
        'maxdownloads' => 55,
    ];
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Shopware\Models\User\Repository
     */
    protected $repo;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->em = Shopware()->Models();
        $this->repo = Shopware()->Models()->getRepository('Shopware\Models\Article\Esd');
    }

    /**
     * Tear down
     */
    protected function tearDown()
    {
        $esd = $this->repo->findOneBy(['file' => '../foobar.pdf']);

        if (!empty($esd)) {
            $this->em->remove($esd);
            $this->em->flush();
        }
        parent::tearDown();
    }

    /**
     * Test case
     */
    public function testGetterAndSetter()
    {
        $esd = new Esd();

        foreach ($this->testData as $field => $value) {
            $setMethod = 'set' . ucfirst($field);
            $getMethod = 'get' . ucfirst($field);

            $esd->$setMethod($value);

            $this->assertEquals($esd->$getMethod(), $value);
        }
    }

    /**
     * Test case
     */
    public function testFromArrayWorks()
    {
        $esd = new Esd();
        $esd->fromArray($this->testData);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($esd->$getMethod(), $value);
        }
    }

    /**detail
     * Test case
     */
    public function testEsdShouldBePersisted()
    {
        $esd = new Esd();

        $articleDetail = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(['active' => true]);
        $esd->setArticleDetail($articleDetail);

        $esd->fromArray($this->testData);

        $this->em->persist($esd);
        $this->em->flush();

        $esdId = $esd->getId();

        // remove esd from entity manager
        $this->em->detach($esd);
        unset($esd);

        $esd = $this->repo->find($esdId);

        foreach ($this->testData as $fieldname => $value) {
            $getMethod = 'get' . ucfirst($fieldname);
            $this->assertEquals($esd->$getMethod(), $value);
        }

        $this->assertInstanceOf('\DateTime', $esd->getDate());
    }
}
