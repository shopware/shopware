<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Category\Gateway\CategoryDenormalization;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CategoryDenormalizationTest extends KernelTestCase
{
    const PRODUCT_UUID = 'SWAG-PRODUCT-UUID-10';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CategoryDenormalization
     */
    private $categoryDenormalization;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');
        $this->categoryDenormalization = $container->get('shopware.category.gateway.category_denormalization');

        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function test_product_assignments()
    {
        $count = $this->categoryDenormalization->buildProductAssignments(SELF::PRODUCT_UUID);
        self::assertEquals(6, $count);
    }

    public function test_to_create_all_assignments()
    {
        $limit = 10;
        $progress = 0;
        $this->categoryDenormalization->removeOrphanedAssignments();
        $this->categoryDenormalization->rebuildCategoryPath();
        $this->categoryDenormalization->removeAllAssignments();

        $count = $this->categoryDenormalization->rebuildAllAssignmentsCount();
        $countNew = 0;

        while ($progress < $count) {
            $countNew += $this->categoryDenormalization->rebuildAllAssignments($limit, $progress);
            $progress += $limit;
        }

        self::assertEquals('323', $count);
        self::assertEquals(1011, $countNew);
        self::assertGreaterThan($count, $countNew);
    }
}
