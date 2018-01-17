<?php declare(strict_types=1);

namespace Shopware\Api\Test\Category\Repository;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Category\Event\Category\CategoryDeletedEvent;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolation;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolationException;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Api\Shop\Definition\ShopTemplateDefinition;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Test\TestWriteContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CategoryRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(CategoryRepository::class);
        $this->connection = $this->container->get('dbal_connection');
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testDeleteParentCategoryDeletesSubCategories()
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->toString(), 'name' => 'parent-1'],
            ['id' => $childId->toString(), 'name' => 'child', 'parentId' => $parentId->toString()],
        ], TranslationContext::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId->toString()]],
            TranslationContext::createDefaultContext()
        );

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);

        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertEquals(
            [$parentId->toString(), $childId->toString()],
            $event->getIds()
        );

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->assertEmpty($exists);
    }

    public function testDeleteChildCategory()
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->toString(), 'name' => 'parent-1'],
            ['id' => $childId->toString(), 'name' => 'child', 'parentId' => $parentId->toString()],
        ], TranslationContext::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);
        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $childId->toString()]],
            TranslationContext::createDefaultContext()
        );

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);
        $event = $result->getEventByDefinition(CategoryDefinition::class);

        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);
        $this->assertEquals([$childId->toString()], $event->getIds());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertEmpty($exists);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertNotEmpty($exists);
    }

    public function testWriterConsidersDeleteParent()
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->toString(), 'name' => 'parent-1'],
            ['id' => $childId->toString(), 'name' => 'child', 'parentId' => $parentId->toString()],
        ], TranslationContext::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete([
            ['id' => $parentId->toString()],
        ], TranslationContext::createDefaultContext());

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);
        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertContains($parentId->toString(), $event->getIds());
        $this->assertContains($childId->toString(), $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testICanNotDeleteShopCategory()
    {
        $categoryId = Uuid::uuid4();
        $shopId = Uuid::uuid4();

        $this->repository->create(
            [['id' => $categoryId->toString(), 'name' => 'System']],
            TranslationContext::createDefaultContext()
        );

        $this->container->get('shopware.api.entity_writer')->insert(
            ShopTemplateDefinition::class,
            [['id' => $shopId->toString(), 'template' => 'Test', 'name' => 'test']],
            TestWriteContext::create()
        );

        $shopRepo = $this->container->get(ShopRepository::class);
        $shopRepo->create([
            [
                'id' => $shopId->toString(),
                'categoryId' => $categoryId->toString(),
                'templateId' => $shopId->toString(),
                'documentTemplateId' => $shopId->toString(),
                'localeId' => '7b52d9dd-2b06-40ec-90be-9f57edf29be7',
                'currencyId' => '4c8eba11-bd35-46d7-86af-bed481a6e665',
                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'paymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'shippingMethodId' => '8beeb66e-9dda-46b1-8891-a059257a590e',
                'countryId' => 'bd5e2dcf-547e-4df6-bb1f-f58a554bc69e',
                'name' => 'test',
                'host' => 'test',
                'basePath' => 'a',
                'baseUrl' => 'a',
                'position' => 1,
            ],
        ], TranslationContext::createDefaultContext());

        try {
            $this->repository->delete(
                [['id' => $categoryId->toString()]],
                TranslationContext::createDefaultContext()
            );
        } catch (RestrictDeleteViolationException $e) {
            $restrictions = $e->getRestrictions();
            /** @var RestrictDeleteViolation $restriction */
            $restriction = array_shift($restrictions);

            $this->assertEquals($categoryId->toString(), $restriction->getId());

            $this->assertArrayHasKey(ShopDefinition::class, $restriction->getRestrictions());
            $this->assertEquals(
                [$shopId->toString()],
                $restriction->getRestrictions()[ShopDefinition::class]
            );
        }
    }
}
