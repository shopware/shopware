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

use Shopware\Models\Category\Category;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Controllers_Backend_CategoryTest extends Enlight_Components_Test_Controller_TestCase
{
    /** @var $model Category */
    protected $repository = null;
    /**
     * dummy data
     *
     * @var array
     */
    private $dummyData = [
         'parentId' => 1,
         'name' => 'unitTestCategory',
         'active' => 1,
    ];

    private $updateMetaDescription = 'testMetaDescription';

    /** @var Shopware\Components\Model\ModelManager */
    private $manager = null;

    /**
     * Standard set up for every test - just disable auth
     */
    public function setUp()
    {
        parent::setUp();

        $this->manager = Shopware()->Models();
        $this->repository = $repository = Shopware()->Models()->getRepository(Category::class);

        // disable auth and acl
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();
    }

    /**
     * test getList controller action
     */
    public function testGetList()
    {
        //delete old data
        $repositoryData = $this->repository->findBy(['name' => $this->dummyData['name']]);
        foreach ($repositoryData as $testDummy) {
            $this->manager->remove($testDummy);
        }
        $this->manager->flush();

        $dummy = $this->createDummy();

        /* @var Enlight_Controller_Response_ResponseTestCase */
        $params['node'] = 1;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/getList');
        $this->assertTrue($this->View()->success);
        $returnData = $this->View()->data;

        $this->assertNotEmpty($returnData);
        $this->assertGreaterThan(0, $this->View()->total);
        $foundDummy = [];
        foreach ($returnData as $dummyData) {
            if ($dummyData['name'] == $dummy->getName()) {
                $foundDummy = $dummyData;
            }
        }
        $this->assertTrue(!empty($foundDummy));
        $this->manager->remove($dummy);
        $this->manager->flush();
    }

    /**
     * test saveDetail controller action
     *
     * @return the id of the new category
     */
    public function testSaveDetail()
    {
        $params = $this->dummyData;
        unset($params['parentId']);
        $params['articles'] = [];
        $params['customerGroups'] = [];

        //test new category
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/createDetail');
        $this->assertTrue($this->View()->success);
        $this->assertEquals($this->dummyData['name'], $this->View()->data['name']);

        //test update category
        $params['id'] = $this->View()->data['id'];
        $params['metaDescription'] = $this->updateMetaDescription;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/updateDetail');
        $this->assertTrue($this->View()->success);
        $this->assertEquals($this->updateMetaDescription, $this->View()->data['metaDescription']);

        return $this->View()->data['id'];
    }

    /**
     * test getDetail controller action
     *
     * @depends testSaveDetail
     *
     * @param $id
     *
     * @return the id to for the testGetDetail Method
     */
    public function testGetDetail($id)
    {
        $params['node'] = $id;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/getDetail');
        $this->assertTrue($this->View()->success);
        $returningData = $this->View()->data;
        $dummyData = $this->dummyData;

        $this->assertEquals($dummyData['parentId'], $returningData['parentId']);
        $this->assertEquals($dummyData['name'], $returningData['name']);
        $this->assertTrue($returningData['changed'] instanceof \DateTime);
        $this->assertTrue($returningData['added'] instanceof \DateTime);

        return $id;
    }

    /**
     * test getIdPath controller method f.e. used by product feed module
     *
     * @depends testGetDetail
     */
    public function testGetIdPath($id)
    {
        $params['categoryIds'] = $id;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/getIdPath');
        $this->assertTrue($this->View()->success);
        $categoryPath = $this->View()->data;
        $this->assertTrue(!empty($categoryPath));
        $this->assertEquals(2, count(explode('/', $categoryPath[0])));
    }

    /**
     * test moveTreeItem controller method
     *
     * @depends testGetDetail
     */
    public function testMoveTreeItem($id)
    {
        //test move to another position
        $params['id'] = $id;
        $params['position'] = 2;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/moveTreeItem');
        $this->assertTrue($this->View()->success);

        $params['id'] = $id;
        $params['position'] = 2;
        $params['parentId'] = 3;
        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/moveTreeItem');
        $this->assertTrue($this->View()->success);

        $movedCategoryModel = $this->repository->find($id);
        $parentModel = $movedCategoryModel->getParent();

        //parentCategory should be Deutsch Id = 3
        $this->assertEquals(3, $parentModel->getId());
    }

    /**
     * test delete controller action
     *
     * @depends testGetDetail
     *
     * @param $id
     */
    public function testDelete($id)
    {
        $params['id'] = $id;
        $categoryModel = $this->repository->find($id);
        $categoryName = $categoryModel->getName();
        $this->assertTrue(!empty($categoryName));

        $this->Request()->setParams($params);
        $this->dispatch('backend/Category/delete');
        $this->assertTrue($this->View()->success);
        $categoryModel = $this->repository->find($id);
        $this->assertEquals(null, $categoryModel);
    }

    /**
     * Creates the dummy data
     *
     * @return Category
     */
    private function getDummyData()
    {
        $dummyModel = new Category();
        $dummyData = $this->dummyData;

        $dummyModel->fromArray($dummyData);
        //set category parent
        $parent = $this->repository->find($dummyData['parentId']);
        $dummyModel->setParent($parent);

        return $dummyModel;
    }

    /**
     * helper method to create the dummy object
     *
     * @return Category
     */
    private function createDummy()
    {
        $dummyData = $this->getDummyData();
        $this->manager->persist($dummyData);
        $this->manager->flush();

        return $dummyData;
    }
}
