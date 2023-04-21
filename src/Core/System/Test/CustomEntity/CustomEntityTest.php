<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\CustomEntity;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MappingEntityClassesException;
use Shopware\Core\Framework\DataAbstractionLayer\Field as DAL;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Entities;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\System\CustomEntity\Xml\Field\BoolField;
use Shopware\Core\System\CustomEntity\Xml\Field\DateField;
use Shopware\Core\System\CustomEntity\Xml\Field\EmailField;
use Shopware\Core\System\CustomEntity\Xml\Field\FloatField;
use Shopware\Core\System\CustomEntity\Xml\Field\IntField;
use Shopware\Core\System\CustomEntity\Xml\Field\JsonField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToManyField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToOneField;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToManyField;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToOneField;
use Shopware\Core\System\CustomEntity\Xml\Field\PriceField;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;
use Shopware\Core\System\CustomEntity\Xml\Field\TextField;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CustomEntityTest extends TestCase
{
    use KernelTestBehaviour;
    use AdminApiTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use AppSystemTestBehaviour;

    private const CATEGORY_TYPE = 'custom-entity-unit-test';

    /**
     * @var array<string, mixed>
     */
    private static array $defaults = [
        'position' => 1,
        'rating' => 2.2,
        'title' => 'Test',
        'content' => 'Test <123>',
        'display' => true,
        'payload' => ['foo' => 'Bar'],
        'email' => 'test@test.com',
    ];

    /**
     * @afterClass
     */
    public static function tearDownSomeOtherSharedFixtures(): void
    {
        $container = KernelLifecycleManager::bootKernel()->getContainer();

        self::cleanUp($container);

        $entities = ['category', 'product'];
        foreach ($entities as $entity) {
            $definition = $container->get(DefinitionInstanceRegistry::class)->getByEntityName($entity);

            foreach ($definition->getFields() as $field) {
                if (\str_starts_with((string) $field->getPropertyName(), 'customEntity')) {
                    $definition->getFields()->remove($field->getPropertyName());
                }
            }
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $result = $container->get('category.repository')->search($criteria, Context::createDefaultContext());

        // ensure that the dal extensions are removed before continue with next test
        static::assertInstanceOf(EntitySearchResult::class, $result);

        $categories = $container->get(Connection::class)->fetchAllAssociative('SELECT LOWER(HEX(id)), `type` FROM category WHERE `type` = :type', ['type' => self::CATEGORY_TYPE]);
        static::assertCount(0, $categories);

        $container->get(Connection::class)->executeStatement('DROP TABLE IF EXISTS `test_with_enum_column`');
    }

    /**
     * Cleanup, schema update and container initialisation costs much time, so we
     * only call this functions once and then execute all none schema updating tests
     * directly
     */
    public function testNoneSchemaChanges(): void
    {
        $container = $this->initBlogEntity();

        $ids = new IdsCollection();

        $this->getContainer()->get(Connection::class)->beginTransaction();

        $this->testStorage($container);

        $this->testCreateFromXml();

        $this->testPersist();

        $this->testAutoPermissions();

        $this->testDefinition($container);

        $this->testRepository($ids, $container);

        $this->testEntityApi($ids);

        $this->testStoreApiAware($ids, $container);

        $this->testEventSystem($ids, $container);

        $this->testInheritance($ids, $container);

        $this->testAllowDisable(false);

        $this->getContainer()->get(Connection::class)->rollBack();

        self::cleanUp($container);
    }

    public function testSchemaUpdate(): void
    {
        $entities = CustomEntityXmlSchema::createFromXmlFile(__DIR__ . '/_fixtures/custom-entity-test/Resources/install.xml');

        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage());

        $this->getContainer()
            ->get(CustomEntitySchemaUpdater::class)
            ->update();

        $schema = $this->getSchema();

        self::assertColumns($schema, 'custom_entity_blog', ['id', 'top_seller_id', 'author_id', 'created_at', 'updated_at', 'position', 'rating']);
        self::assertColumns($schema, 'ce_blog_comment', ['id', 'created_at', 'updated_at']);
        self::assertColumns($schema, 'custom_entity_to_remove', ['id', 'created_at', 'updated_at']);

        $entities = CustomEntityXmlSchema::createFromXmlFile(__DIR__ . '/_fixtures/custom-entity-test/Resources/update.xml');
        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage());

        $this->getContainer()
            ->get(CustomEntitySchemaUpdater::class)
            ->update();

        $schema = $this->getSchema();

        self::assertColumns($schema, 'custom_entity_blog', ['id', 'created_at', 'updated_at', 'rating', 'payload', 'email']);
        self::assertColumns($schema, 'ce_blog_comment', ['id', 'created_at', 'updated_at', 'email']);

        static::assertFalse($schema->getTable('product')->hasColumn('ce_blog_comment_products_reverse_id'));
        static::assertFalse($schema->getTable('product')->hasColumn('custom_entity_to_remove_products_reverse_id'));

        static::assertFalse($schema->hasTable('custom_entity_blog_product'));
        static::assertFalse($schema->hasTable('custom_entity_to_remove'));

        self::cleanUp($this->getContainer());
    }

    public function testAllowDisableIsTrueIfNoRestrictDeleteIsUsed(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/without-restrict-delete');

        $container = KernelLifecycleManager::bootKernel()->getContainer();

        $this->testAllowDisable(true);

        self::cleanUp($container);
    }

    public function testDoesNotRegisterCustomEntitiesIfAppIsInactive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/without-restrict-delete', false);

        $schema = $this->getSchema();

        static::assertFalse($schema->hasTable('custom_entity_blog_product'));
        static::assertFalse($schema->hasTable('custom_entity_to_remove'));

        self::cleanUp($this->getContainer());
    }

    public function testPersistsCustomEntitiesIfSchemaContainsEnumColumns(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement('
            CREATE TABLE test_with_enum_column (
                id BINARY(16) NOT NULL PRIMARY KEY,
                enum_column ENUM(\'foo\', \'bar\') DEFAULT NULL
            )
        ');

        $columns = $connection->executeQuery('DESCRIBE test_with_enum_column')->fetchAllAssociative();

        $this->loadAppsFromDir(__DIR__ . '/_fixtures/custom-entity-test', false);

        $schema = $this->getSchema();
        static::assertTrue($schema->hasTable('test_with_enum_column'));
        static::assertTrue($schema->hasTable('custom_entity_blog'));
        static::assertTrue($schema->hasTable('ce_blog_comment'));
        static::assertEquals($columns, $connection->executeQuery('DESCRIBE test_with_enum_column')->fetchAllAssociative());

        self::cleanUp($this->getContainer());
    }

    private function testStorage(ContainerInterface $container): void
    {
        $schema = $container
            ->get(Connection::class)
            ->createSchemaManager()
            ->introspectSchema();

        self::assertColumns($schema, 'custom_entity_blog', ['id', 'top_seller_restrict_id', 'top_seller_restrict_version_id', 'top_seller_cascade_id', 'top_seller_cascade_version_id', 'top_seller_set_null_id', 'top_seller_set_null_version_id', 'link_product_restrict_id', 'link_product_restrict_version_id', 'link_product_cascade_id', 'link_product_cascade_version_id', 'link_product_set_null_id', 'link_product_set_null_version_id', 'inherited_top_seller_id', 'inherited_top_seller_version_id', 'created_at', 'updated_at', 'position', 'rating', 'payload', 'email']);
        self::assertColumns($schema, 'custom_entity_blog_translation', ['custom_entity_blog_id', 'language_id', 'created_at', 'updated_at', 'title', 'content', 'display']);
        self::assertColumns($schema, 'ce_blog_comment', ['id', 'recommendation_id', 'recommendation_version_id', 'created_at', 'updated_at', 'email']);
        self::assertColumns($schema, 'ce_blog_comment_translation', ['ce_blog_comment_id', 'language_id', 'created_at', 'updated_at', 'title', 'content']);
        self::assertColumns($schema, 'custom_entity_blog_products', ['custom_entity_blog_id', 'product_id', 'product_version_id']);
        self::assertColumns($schema, 'product', ['customEntityBlogInheritedProducts', 'customEntityBlogInheritedTopSeller']);
        self::assertColumns($schema, 'category', ['custom_entity_blog_links_restrict_id', 'custom_entity_blog_links_set_null_id']);
    }

    private function testEventSystem(IdsCollection $ids, ContainerInterface $container): void
    {
        /** @var EntityRepository $blogRepository */
        $blogRepository = $container->get('custom_entity_blog.repository');

        $blogRepository->create([self::blog('blog-4', $ids)], Context::createDefaultContext());

        $event = $blogRepository->delete([['id' => $ids->get('blog-4')]], Context::createDefaultContext());

        static::assertSame(EntityWrittenContainerEvent::class, $event::class);
        static::assertCount(1, $event->getPrimaryKeys('custom_entity_blog'));
        static::assertContains($ids->get('blog-4'), $event->getPrimaryKeys('custom_entity_blog'));

        $cascade = $event->getPrimaryKeys('custom_entity_blog_products');
        static::assertCount(2, $cascade);

        $cascade = $event->getPrimaryKeys('ce_blog_comment');
        static::assertCount(2, $cascade);

        $cascade = $event->getPrimaryKeys('ce_blog_comment_translation');
        static::assertCount(2, $cascade);

        $cascade = $event->getPrimaryKeys('custom_entity_blog_translation');
        static::assertCount(1, $cascade);

        $categories = $event->getEventByEntityName('category');

        static::assertInstanceOf(EntityWrittenEvent::class, $categories);
        static::assertContains($ids->get('blog-4-c-set-null-1'), $categories->getIds());
        static::assertContains($ids->get('blog-4-c-set-null-2'), $categories->getIds());

        foreach ($categories->getWriteResults() as $result) {
            static::assertArrayHasKey('customEntityBlogLinksSetNullId', $result->getPayload());
            static::assertNull($result->getProperty('customEntityBlogLinksSetNullId'));
        }

        $blog = self::blog('blog-5', $ids);
        $blog['topSellerRestrict'] = (new ProductBuilder($ids, 'top-seller-restrict'))->price(100)->build();
        $blog['linksRestrict'] = [['id' => $ids->get('category-restrict-delete'), 'name' => 'test', 'type' => self::CATEGORY_TYPE]];

        $blogRepository->create([$blog], Context::createDefaultContext());

        try {
            $container->get('product.repository')->delete([['id' => $ids->get('top-seller-restrict')]], Context::createDefaultContext());
            static::fail('Expected delete restricted exception');
        } catch (\Exception $e) {
            static::assertInstanceOf(RestrictDeleteViolationException::class, $e);
        }

        try {
            $blogRepository->delete([['id' => $ids->get('blog-5')]], Context::createDefaultContext());
            static::fail('Expected delete restricted exception');
        } catch (\Exception $e) {
            static::assertInstanceOf(RestrictDeleteViolationException::class, $e);
        }

        // test correct order
        $container->get('category.repository')->delete([['id' => $ids->get('category-restrict-delete')]], Context::createDefaultContext());
        $blogRepository->delete([['id' => $ids->get('blog-5')]], Context::createDefaultContext());
        $container->get('product.repository')->delete([['id' => $ids->get('top-seller-restrict')]], Context::createDefaultContext());

        $blogRepository->create([self::blog('blog-6', $ids)], Context::createDefaultContext());

        $event = $container->get('product.repository')->delete([['id' => $ids->get('blog-6-top-seller-set-null')]], Context::createDefaultContext());
        $blogs = $event->getEventByEntityName('custom_entity_blog');
        static::assertInstanceOf(EntityWrittenEvent::class, $blogs);
        static::assertCount(1, $blogs->getIds());
        static::assertCount(1, $blogs->getWriteResults());
        static::assertArrayHasKey('topSellerSetNullId', $blogs->getWriteResults()[0]->getPayload());
        static::assertNull($blogs->getWriteResults()[0]->getProperty('topSellerSetNullId'));

        $event = $container->get('product.repository')->delete([['id' => $ids->get('blog-6-link-product-set-null')]], Context::createDefaultContext());
        $blogs = $event->getEventByEntityName('custom_entity_blog');
        static::assertInstanceOf(EntityWrittenEvent::class, $blogs);
        static::assertCount(1, $blogs->getIds());
        static::assertCount(1, $blogs->getWriteResults());
        static::assertArrayHasKey('linkProductSetNullId', $blogs->getWriteResults()[0]->getPayload());
        static::assertNull($blogs->getWriteResults()[0]->getProperty('linkProductSetNullId'));
    }

    private function testInheritance(IdsCollection $ids, ContainerInterface $container): void
    {
        $this->testOneToOneInheritance($ids, $container);
        $this->testManyToManyInheritance($ids, $container);
        $this->testManyToOneInheritance($ids, $container);
    }

    private function testManyToManyInheritance(IdsCollection $ids, ContainerInterface $container): void
    {
        $blog1 = [...self::$defaults, ...['id' => $ids->get('inh.blog.1')]];
        $blog2 = [...self::$defaults, ...['id' => $ids->get('inh.blog.2')]];

        $product = (new ProductBuilder($ids, 'inheritance'))
            ->price(100)
            ->add('customEntityBlogInheritedProducts', [$blog1])
            ->variant(
                (new ProductBuilder($ids, 'v1'))
                    ->price(100)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($ids, 'v2'))
                    ->price(100)
                    ->add('customEntityBlogInheritedProducts', [$blog2])
                    ->build()
            )
            ->build();

        $event = $container->get('product.repository')
            ->upsert([$product], Context::createDefaultContext());

        static::assertNotEmpty($event->getPrimaryKeys('product'));
        static::assertNotEmpty($event->getPrimaryKeys('custom_entity_blog'));
        static::assertNotEmpty($event->getPrimaryKeys('custom_entity_blog_inherited_products'));

        static::assertContains($ids->get('inh.blog.1'), $event->getPrimaryKeys('custom_entity_blog'));
        static::assertContains($ids->get('inh.blog.2'), $event->getPrimaryKeys('custom_entity_blog'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['v1', 'v2']));
        $criteria->addAssociation('customEntityBlogInheritedProducts');

        $products = $container->get('product.repository')->search($criteria, $context);

        static::assertCount(2, $products);
        $v1 = $products->get($ids->get('v1'));

        static::assertInstanceOf(ProductEntity::class, $v1);
        static::assertTrue($v1->hasExtension('customEntityBlogInheritedProducts'));
        $inheritedProductsExtension = $v1->getExtension('customEntityBlogInheritedProducts');
        static::assertInstanceOf(EntityCollection::class, $inheritedProductsExtension);
        static::assertCount(1, $inheritedProductsExtension);
        $blog = $inheritedProductsExtension->first();
        static::assertInstanceOf(ArrayEntity::class, $blog);
        static::assertEquals($blog1['id'], $blog->getId());

        $v2 = $products->get($ids->get('v2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v2->hasExtension('customEntityBlogInheritedProducts'));
        $inheritedProductsExtension = $v2->getExtension('customEntityBlogInheritedProducts');
        static::assertInstanceOf(EntityCollection::class, $inheritedProductsExtension);
        static::assertCount(1, $inheritedProductsExtension);
        $blog = $inheritedProductsExtension->first();
        static::assertInstanceOf(ArrayEntity::class, $blog);
        static::assertEquals($blog2['id'], $blog->getId());

        /** @var EntityRepository $blogRepository */
        $blogRepository = $container->get('custom_entity_blog.repository');
        $blogRepository->delete([['id' => $ids->get('inh.blog.2')]], $context);

        $criteria = new Criteria($ids->getList(['v2']));
        $criteria->addAssociation('customEntityBlogInheritedProducts');
        $products = $container->get('product.repository')->search($criteria, $context);

        $v2 = $products->get($ids->get('v2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v2->hasExtension('customEntityBlogInheritedProducts'));
        $inheritedProductsExtension = $v2->getExtension('customEntityBlogInheritedProducts');
        static::assertInstanceOf(EntityCollection::class, $inheritedProductsExtension);
        static::assertCount(1, $inheritedProductsExtension);
        $blog = $inheritedProductsExtension->first();
        static::assertInstanceOf(ArrayEntity::class, $blog);
        static::assertEquals($blog1['id'], $blog->getId());
    }

    private function testOneToOneInheritance(IdsCollection $ids, ContainerInterface $container): void
    {
        $blog1 = [...self::$defaults, ...['id' => $ids->get('inh.one-to-one.1')]];
        $blog2 = [...self::$defaults, ...['id' => $ids->get('inh.one-to-one.2')]];

        $product = (new ProductBuilder($ids, 'inheritance'))
            ->price(100)
            ->add('customEntityBlogInheritedLinkProduct', $blog1)
            ->variant(
                (new ProductBuilder($ids, 'one-to-one-1'))->price(100)->build()
            )
            ->variant(
                (new ProductBuilder($ids, 'one-to-one-2'))
                    ->price(100)
                    ->add('customEntityBlogInheritedLinkProduct', $blog2)
                    ->build()
            )
            ->build();

        $event = $container->get('product.repository')
            ->upsert([$product], Context::createDefaultContext());

        static::assertNotEmpty($event->getPrimaryKeys('product'));
        static::assertNotEmpty($event->getPrimaryKeys('custom_entity_blog'));

        static::assertContains($ids->get('inh.one-to-one.1'), $event->getPrimaryKeys('custom_entity_blog'));
        static::assertContains($ids->get('inh.one-to-one.2'), $event->getPrimaryKeys('custom_entity_blog'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['one-to-one-1', 'one-to-one-2']));
        $criteria->addAssociation('customEntityBlogInheritedLinkProduct');

        $products = $container->get('product.repository')->search($criteria, $context);

        static::assertCount(2, $products);
        $v1 = $products->get($ids->get('one-to-one-1'));

        static::assertInstanceOf(ProductEntity::class, $v1);
        static::assertTrue($v1->hasExtension('customEntityBlogInheritedLinkProduct'));
        static::assertInstanceOf(ArrayEntity::class, $v1->getExtension('customEntityBlogInheritedLinkProduct'));
        static::assertEquals($blog1['id'], $v1->getExtension('customEntityBlogInheritedLinkProduct')->getId());

        $v2 = $products->get($ids->get('one-to-one-2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v2->hasExtension('customEntityBlogInheritedLinkProduct'));
        static::assertInstanceOf(ArrayEntity::class, $v2->getExtension('customEntityBlogInheritedLinkProduct'));
        static::assertEquals($blog2['id'], $v2->getExtension('customEntityBlogInheritedLinkProduct')->getId());

        $context->addState('debug');
        /** @var EntityRepository $blogRepository */
        $blogRepository = $container->get('custom_entity_blog.repository');
        $blogRepository->delete([['id' => $ids->get('inh.one-to-one.2')]], $context);

        $criteria = new Criteria($ids->getList(['one-to-one-2']));
        $criteria->addAssociation('customEntityBlogInheritedLinkProduct');
        $products = $container->get('product.repository')->search($criteria, $context);

        $v2 = $products->get($ids->get('one-to-one-2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v1->hasExtension('customEntityBlogInheritedLinkProduct'));
        static::assertInstanceOf(ArrayEntity::class, $v2->getExtension('customEntityBlogInheritedLinkProduct'));
        static::assertEquals($blog1['id'], $v2->getExtension('customEntityBlogInheritedLinkProduct')->getId());
    }

    private function testManyToOneInheritance(IdsCollection $ids, ContainerInterface $container): void
    {
        $blog1 = [...self::$defaults, ...['id' => $ids->get('inh.many-to-one.1')]];
        $blog2 = [...self::$defaults, ...['id' => $ids->get('inh.many-to-one.2')]];

        $product = (new ProductBuilder($ids, 'inheritance'))
            ->price(100)
            ->add('customEntityBlogInheritedTopSeller', [$blog1])
            ->variant(
                (new ProductBuilder($ids, 'many-to-one-1'))
                    ->price(100)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($ids, 'many-to-one-2'))
                    ->price(100)
                    ->add('customEntityBlogInheritedTopSeller', [$blog2])
                    ->build()
            )
            ->build();

        $event = $container->get('product.repository')
            ->upsert([$product], Context::createDefaultContext());

        static::assertNotEmpty($event->getPrimaryKeys('product'));
        static::assertNotEmpty($event->getPrimaryKeys('custom_entity_blog'));

        static::assertContains($ids->get('inh.many-to-one.1'), $event->getPrimaryKeys('custom_entity_blog'));
        static::assertContains($ids->get('inh.many-to-one.2'), $event->getPrimaryKeys('custom_entity_blog'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['many-to-one-1', 'many-to-one-2']));
        $criteria->addAssociation('customEntityBlogInheritedTopSeller');

        $products = $container->get('product.repository')->search($criteria, $context);

        static::assertCount(2, $products);
        $v1 = $products->get($ids->get('many-to-one-1'));

        static::assertInstanceOf(ProductEntity::class, $v1);
        static::assertTrue($v1->hasExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(EntityCollection::class, $v1->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertCount(1, $v1->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(ArrayEntity::class, $v1->getExtension('customEntityBlogInheritedTopSeller')->first());
        static::assertEquals($blog1['id'], $v1->getExtension('customEntityBlogInheritedTopSeller')->first()->getId());

        $v2 = $products->get($ids->get('many-to-one-2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v2->hasExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(EntityCollection::class, $v2->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertCount(1, $v2->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(ArrayEntity::class, $v2->getExtension('customEntityBlogInheritedTopSeller')->first());
        static::assertEquals($blog2['id'], $v2->getExtension('customEntityBlogInheritedTopSeller')->first()->getId());

        /** @var EntityRepository $blogRepository */
        $blogRepository = $container->get('custom_entity_blog.repository');
        $blogRepository->delete([['id' => $ids->get('inh.many-to-one.2')]], $context);

        $criteria = new Criteria($ids->getList(['many-to-one-2']));
        $criteria->addAssociation('customEntityBlogInheritedTopSeller');
        $products = $container->get('product.repository')->search($criteria, $context);

        $v2 = $products->get($ids->get('many-to-one-2'));
        static::assertInstanceOf(ProductEntity::class, $v2);
        static::assertTrue($v1->hasExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(EntityCollection::class, $v2->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertCount(1, $v2->getExtension('customEntityBlogInheritedTopSeller'));
        static::assertInstanceOf(ArrayEntity::class, $v2->getExtension('customEntityBlogInheritedTopSeller')->first());
        static::assertEquals($blog1['id'], $v2->getExtension('customEntityBlogInheritedTopSeller')->first()->getId());
    }

    /**
     * @param list<string> $columns
     */
    private static function assertColumns(Schema $schema, string $table, array $columns): void
    {
        static::assertTrue($schema->hasTable($table), \sprintf('Table %s do not exists', $table));

        $existing = \array_keys($schema->getTable($table)->getColumns());

        foreach ($columns as $column) {
            // strtolower required for assertContains
            static::assertContains(\strtolower($column), $existing, 'Column ' . $column . ' not found in table ' . $table . ': ' . \print_r($existing, true));
        }
    }

    private function testCreateFromXml(): void
    {
        $entities = CustomEntityXmlSchema::createFromXmlFile(__DIR__ . '/_fixtures/custom-entity-test/Resources/entities.xml');

        $expected = new CustomEntityXmlSchema(
            __DIR__ . '/_fixtures/custom-entity-test/Resources',
            new Entities([
                new Entity([
                    'name' => 'custom_entity_blog',
                    'fields' => [
                        new IntField(['name' => 'position', 'storeApiAware' => true]),
                        new FloatField(['name' => 'rating', 'storeApiAware' => true]),
                        new StringField(['name' => 'title', 'storeApiAware' => true, 'required' => true, 'translatable' => true]),
                        new TextField(['name' => 'content', 'storeApiAware' => true, 'allowHtml' => true, 'translatable' => true]),
                        new BoolField(['name' => 'display', 'storeApiAware' => true, 'translatable' => true]),
                        new JsonField(['name' => 'payload', 'storeApiAware' => false]),
                        new EmailField(['name' => 'email', 'storeApiAware' => false]),
                        new PriceField(['name' => 'price', 'storeApiAware' => false]),
                        new DateField(['name' => 'my_date', 'storeApiAware' => false]),
                        new ManyToManyField(['name' => 'products', 'storeApiAware' => true, 'reference' => 'product', 'inherited' => false]),
                        new ManyToOneField(['name' => 'top_seller_restrict', 'storeApiAware' => true, 'reference' => 'product', 'required' => false, 'inherited' => false, 'onDelete' => 'restrict']),
                        new ManyToOneField(['name' => 'top_seller_cascade', 'storeApiAware' => true, 'reference' => 'product', 'required' => true, 'inherited' => false, 'onDelete' => 'cascade']),
                        new ManyToOneField(['name' => 'top_seller_set_null', 'storeApiAware' => true, 'reference' => 'product', 'inherited' => false, 'onDelete' => 'set-null']),
                        new OneToOneField(['name' => 'link_product_restrict', 'storeApiAware' => false, 'reference' => 'product', 'inherited' => false, 'onDelete' => 'restrict']),
                        new OneToOneField(['name' => 'link_product_cascade', 'storeApiAware' => false, 'reference' => 'product', 'inherited' => false, 'onDelete' => 'cascade']),
                        new OneToOneField(['name' => 'link_product_set_null', 'storeApiAware' => false, 'reference' => 'product', 'inherited' => false, 'onDelete' => 'set-null']),
                        new OneToManyField(['name' => 'links_restrict', 'storeApiAware' => true, 'reference' => 'category', 'onDelete' => 'restrict']),
                        new OneToManyField(['name' => 'links_set_null', 'storeApiAware' => true, 'reference' => 'category', 'onDelete' => 'set-null']),

                        new OneToManyField(['name' => 'comments', 'storeApiAware' => true, 'reference' => 'ce_blog_comment', 'onDelete' => 'cascade', 'reverseRequired' => true]),

                        new ManyToManyField(['name' => 'inherited_products', 'storeApiAware' => true, 'reference' => 'product', 'inherited' => true]),
                        new ManyToOneField(['name' => 'inherited_top_seller', 'storeApiAware' => true, 'reference' => 'product', 'required' => false, 'inherited' => true, 'onDelete' => 'set-null']),
                        new OneToOneField(['name' => 'inherited_link_product', 'storeApiAware' => true, 'reference' => 'product', 'required' => false, 'inherited' => true, 'onDelete' => 'set-null']),
                    ],
                ]),
                new Entity([
                    'name' => 'ce_blog_comment',
                    'fields' => [
                        new StringField(['name' => 'title', 'storeApiAware' => true, 'required' => true, 'translatable' => true]),
                        new TextField(['name' => 'content', 'storeApiAware' => true, 'allowHtml' => true, 'translatable' => true]),
                        new EmailField(['name' => 'email', 'storeApiAware' => false]),
                        new ManyToOneField(['name' => 'recommendation', 'reference' => 'product', 'storeApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),
                    ],
                ]),
            ])
        );

        static::assertEquals($expected, $entities);
    }

    private function testPersist(): void
    {
        $storage = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM custom_entity ORDER BY name DESC');

        static::assertCount(2, $storage);

        $fields = [
            ['name' => 'position', 'type' => 'int', 'storeApiAware' => true, 'required' => false],
            ['name' => 'rating', 'type' => 'float', 'storeApiAware' => true, 'required' => false],
            ['name' => 'title', 'type' => 'string', 'required' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'content', 'type' => 'text', 'allowHtml' => true, 'translatable' => true, 'storeApiAware' => true, 'required' => false],
            ['name' => 'display', 'type' => 'bool', 'translatable' => true, 'storeApiAware' => true, 'required' => false],
            ['name' => 'payload', 'type' => 'json', 'storeApiAware' => false, 'required' => false],
            ['name' => 'email', 'type' => 'email', 'storeApiAware' => false, 'required' => false],
            ['name' => 'price', 'type' => 'price', 'storeApiAware' => false, 'required' => false],
            ['name' => 'my_date', 'type' => 'date', 'storeApiAware' => false, 'required' => false],
            ['name' => 'products', 'type' => 'many-to-many', 'reference' => 'product', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'cascade'],
            ['name' => 'top_seller_restrict', 'type' => 'many-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'restrict'],
            ['name' => 'top_seller_cascade', 'type' => 'many-to-one', 'required' => true, 'reference' => 'product', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'cascade'],
            ['name' => 'top_seller_set_null', 'type' => 'many-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'set-null'],
            ['name' => 'link_product_restrict', 'type' => 'one-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => false, 'inherited' => false, 'onDelete' => 'restrict'],
            ['name' => 'link_product_cascade', 'type' => 'one-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => false, 'inherited' => false, 'onDelete' => 'cascade'],
            ['name' => 'link_product_set_null', 'type' => 'one-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => false, 'inherited' => false, 'onDelete' => 'set-null'],
            ['name' => 'links_restrict', 'type' => 'one-to-many', 'reference' => 'category', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'restrict', 'reverseRequired' => false],
            ['name' => 'links_set_null', 'type' => 'one-to-many', 'reference' => 'category', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'set-null', 'reverseRequired' => false],
            ['name' => 'comments', 'type' => 'one-to-many', 'reference' => 'ce_blog_comment', 'storeApiAware' => true, 'inherited' => false, 'onDelete' => 'cascade', 'reverseRequired' => true],
            ['name' => 'inherited_products', 'type' => 'many-to-many', 'reference' => 'product', 'storeApiAware' => true, 'inherited' => true, 'onDelete' => 'cascade'],
            ['name' => 'inherited_top_seller', 'type' => 'many-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => true, 'inherited' => true, 'onDelete' => 'set-null'],
            ['name' => 'inherited_link_product', 'type' => 'one-to-one', 'required' => false, 'reference' => 'product', 'storeApiAware' => true, 'inherited' => true, 'onDelete' => 'set-null'],
        ];

        static::assertEquals('custom_entity_blog', $storage[0]['name']);
        static::assertEquals($fields, json_decode((string) $storage[0]['fields'], true, 512, \JSON_THROW_ON_ERROR));

        $fields = [
            ['name' => 'title', 'type' => 'string', 'required' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'content', 'type' => 'text', 'required' => false, 'allowHtml' => true, 'translatable' => true, 'storeApiAware' => true],
            ['name' => 'email', 'type' => 'email', 'required' => false, 'storeApiAware' => false],
            ['name' => 'recommendation', 'type' => 'many-to-one', 'reference' => 'product', 'storeApiAware' => true, 'required' => false, 'inherited' => false, 'onDelete' => 'set-null'],
        ];
        static::assertEquals('ce_blog_comment', $storage[1]['name']);
        static::assertEquals($fields, json_decode((string) $storage[1]['fields'], true, 512, \JSON_THROW_ON_ERROR));

        static::assertNotNull($storage[0]['created_at']);
        static::assertNotNull($storage[1]['created_at']);

        $entities = CustomEntityXmlSchema::createFromXmlFile(__DIR__ . '/_fixtures/custom-entity-test/Resources/entities.xml');

        $this->getContainer()
            ->get(CustomEntityPersister::class)
            ->update($entities->toStorage());

        $storage = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM custom_entity ORDER BY name');

        static::assertCount(2, $storage);
        static::assertNotNull($storage[0]['updated_at']);
        static::assertNotNull($storage[1]['updated_at']);
    }

    private function testRepository(IdsCollection $ids, ContainerInterface $container): void
    {
        $blogs = self::blog('blog-2', $ids);

        $repository = $container->get('custom_entity_blog.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        $manyToManyRepo = $container->get('custom_entity_blog_inherited_products.repository');
        static::assertInstanceOf(EntityRepository::class, $manyToManyRepo);

        $exceptionWasThrown = false;

        try {
            $manyToManyRepo->search(new Criteria(), Context::createDefaultContext());
        } catch (MappingEntityClassesException) {
            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Excepted exception to be thrown.');
        }

        $repository->create([$blogs], Context::createDefaultContext());

        $criteria = new Criteria($ids->getList(['blog-2']));
        $criteria->addAssociation('products');
        $criteria->addAssociation('comments');
        $criteria->addAssociation('topSellerCascade');
        $criteria->addAssociation('topSellerSetNull');
        $criteria->addAssociation('linkProductCascade');
        $criteria->addAssociation('linkProductSetNull');
        $criteria->addAssociation('linksSetNull');

        $blogs = $repository->search($criteria, Context::createDefaultContext());

        static::assertCount(1, $blogs);
        $blog = $blogs->first();

        static::assertEquals($ids->get('blog-2'), $blog->get('id'));
        static::assertEquals(1, $blog->get('position'));
        static::assertEquals(2.2, $blog->get('rating'));
        static::assertEquals('blog-2', $blog->get('title'));
        static::assertEquals('Test &lt;123&gt;', $blog->get('content'));
        static::assertTrue($blog->get('display'));
        static::assertEquals(['foo' => 'Bar'], $blog->get('payload'));
        static::assertEquals('test@test.com', $blog->get('email'));
        static::assertInstanceOf(\DateTimeImmutable::class, $blog->get('myDate'));
        static::assertEquals(new PriceCollection([new Price(Defaults::CURRENCY, 10, 10, false)]), $blog->get('price'));

        static::assertInstanceOf(ArrayEntity::class, $blog);
        static::assertEquals($ids->get('blog-2'), $blog->getId());
        static::assertInstanceOf(ProductEntity::class, $blog->get('topSellerCascade'));
        static::assertInstanceOf(ProductEntity::class, $blog->get('topSellerSetNull'));
        static::assertInstanceOf(ProductEntity::class, $blog->get('linkProductCascade'));
        static::assertInstanceOf(ProductEntity::class, $blog->get('linkProductSetNull'));
        static::assertCount(2, $blog->get('products'));
        static::assertCount(2, $blog->get('comments'));
        static::assertCount(2, $blog->get('linksSetNull'));
    }

    private function testEntityApi(IdsCollection $ids): void
    {
        $client = $this->getBrowser();

        // create
        $client->request('POST', '/api/custom-entity-blog', [], [], [], json_encode(self::blog('blog-1', $ids), \JSON_THROW_ON_ERROR));
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r((string) $response->getContent(), true));

        // update
        $client->request(
            'PATCH',
            '/api/custom-entity-blog/' . $ids->get('blog-1'),
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            \json_encode(['id' => $ids->get('blog-1'), 'title' => 'update'], \JSON_THROW_ON_ERROR)
        );
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        // list
        $client->request('GET', '/api/custom-entity-blog', ['ids' => [$ids->get('blog-1')]], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = $client->getResponse();
        $body = json_decode((string) $response->getContent(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));
        static::assertArrayHasKey('total', $body);
        static::assertArrayHasKey('data', $body);
        static::assertArrayHasKey('aggregations', $body);
        static::assertCount(1, $body['data']);
        static::assertEquals(1, $body['total']);
        static::assertEquals('update', $body['data'][0]['title']);
        static::assertEquals($ids->get('blog-1'), $body['data'][0]['id']);
        static::assertArrayHasKey('inheritedProducts', $body['data'][0]);
        static::assertNull($body['data'][0]['inheritedProducts']);

        // detail
        $client->request(
            'GET',
            '/api/custom-entity-blog/' . $ids->get('blog-1'),
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            \json_encode(['ids' => [$ids->get('blog-1')]], \JSON_THROW_ON_ERROR)
        );
        $response = $client->getResponse();
        $body = json_decode((string) $response->getContent(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));
        static::assertArrayHasKey('data', $body);
        static::assertEquals('update', $body['data']['title']);
        static::assertEquals($ids->get('blog-1'), $body['data']['id']);
        static::assertArrayHasKey('inheritedProducts', $body['data']);
        static::assertNull($body['data']['inheritedProducts']);

        // search
        $client->request(
            'POST',
            '/api/search/custom-entity-blog',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            \json_encode(['ids' => [$ids->get('blog-1')]], \JSON_THROW_ON_ERROR)
        );
        $response = $client->getResponse();
        $body = json_decode((string) $response->getContent(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));
        static::assertArrayHasKey('total', $body);
        static::assertArrayHasKey('data', $body);
        static::assertArrayHasKey('aggregations', $body);
        static::assertCount(1, $body['data']);
        static::assertEquals(1, $body['total']);
        static::assertEquals('update', $body['data'][0]['title']);
        static::assertEquals($ids->get('blog-1'), $body['data'][0]['id']);
        static::assertArrayHasKey('inheritedProducts', $body['data'][0]);
        static::assertNull($body['data'][0]['inheritedProducts']);

        // search-ids
        $client->request(
            'POST',
            '/api/search-ids/custom-entity-blog',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            \json_encode(['ids' => [$ids->get('blog-1')]], \JSON_THROW_ON_ERROR)
        );
        $response = $client->getResponse();
        $body = json_decode((string) $response->getContent(), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));
        static::assertArrayHasKey('total', $body);
        static::assertArrayHasKey('data', $body);
        static::assertCount(1, $body['data']);
        static::assertEquals(1, $body['total']);
        static::assertEquals($ids->get('blog-1'), $body['data'][0]);

        $client->request(
            'DELETE',
            '/api/custom-entity-blog/' . $ids->get('blog-1'),
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            \json_encode(['ids' => [$ids->get('blog-1')]], \JSON_THROW_ON_ERROR)
        );
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));
    }

    private function testStoreApiAware(IdsCollection $ids, ContainerInterface $container): void
    {
        /** @var EntityRepository $blogRepository */
        $blogRepository = $container->get('custom_entity_blog.repository');
        $blogRepository->create([self::blog('blog-3', $ids)], Context::createDefaultContext());

        $criteria = [
            'ids' => [$ids->get('blog-3')],
            'includes' => [
                'custom_entity_blog' => ['id', 'title', 'rating', 'content', 'email', 'comments', 'linkProductCascade', 'topSellerCascade', 'translated'],
                'ce_blog_comment' => ['title', 'content', 'email'],
                'product' => ['name', 'productNumber', 'price'],
                'dal_entity_search_result' => ['elements'],
            ],
            'associations' => [
                'topSellerCascade' => [],
                'linkProductCascade' => [],
                'comments' => [],
            ],
        ];

        $browser = $this->getSalesChannelBrowser();
        $browser->request('POST', '/store-api/script/blog', $criteria);

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $salesChannelId = $browser->getServerParameter('test-sales-channel-id');
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM sales_channel WHERE id = :id', ['id' => Uuid::fromHexToBytes($salesChannelId)]);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), print_r($response, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-blog::response', $traces);
        static::assertCount(1, $traces['store-api-blog::response']);
        static::assertSame('some debug information', $traces['store-api-blog::response'][0]['output'][0]);

        $expected = [
            'apiAlias' => 'store_api_blog_response',
            'blogs' => [
                'apiAlias' => 'dal_entity_search_result',
                'elements' => [
                    [
                        'id' => $ids->get('blog-3'),
                        'rating' => 2.2,
                        'title' => 'blog-3',
                        'content' => 'Test &lt;123&gt;',
                        'translated' => [
                            'title' => 'blog-3',
                            'content' => 'Test &lt;123&gt;',
                        ],
                        'topSellerCascade' => [
                            'productNumber' => 'blog-3-top-seller-cascade',
                            'name' => 'blog-3-top-seller-cascade',
                            'apiAlias' => 'product',
                        ],
                        'comments' => [
                            ['title' => 'test', 'content' => 'test', 'apiAlias' => 'ce_blog_comment'],
                            ['title' => 'test', 'content' => 'test', 'apiAlias' => 'ce_blog_comment'],
                        ],
                        'apiAlias' => 'custom_entity_blog',
                    ],
                ],
            ],
        ];
        static::assertEquals($expected, $response);

        static::assertArrayNotHasKey('linkProductCascade', $response['blogs']['elements'][0]);
        static::assertArrayNotHasKey('price', $response['blogs']['elements'][0]['topSellerCascade']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function blog(string $key, IdsCollection $ids): array
    {
        return [
            'id' => $ids->get($key),
            'position' => 1,
            'rating' => 2.2,
            'title' => $key,
            'content' => 'Test &lt;123&gt;',
            'display' => true,
            'payload' => ['foo' => 'Bar'],
            'email' => 'test@test.com',
            'myDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
            ],
            'comments' => [
                ['title' => 'test', 'content' => 'test', 'email' => 'test@test.com'],
                ['title' => 'test', 'content' => 'test', 'email' => 'test@test.com'],
            ],
            'linksSetNull' => [
                ['id' => $ids->get($key . '-c-set-null-1'), 'name' => 'test', 'type' => self::CATEGORY_TYPE],
                ['id' => $ids->get($key . '-c-set-null-2'), 'name' => 'test', 'type' => self::CATEGORY_TYPE],
            ],
            'topSellerCascade' => (new ProductBuilder($ids, $key . '-top-seller-cascade'))->price(100)->build(),
            'topSellerSetNull' => (new ProductBuilder($ids, $key . '-top-seller-set-null'))->price(100)->build(),
            'linkProductCascade' => (new ProductBuilder($ids, $key . '-link-product-cascade'))->price(100)->build(),
            'linkProductSetNull' => (new ProductBuilder($ids, $key . '-link-product-set-null'))->price(100)->build(),
            'products' => [
                (new ProductBuilder($ids, $key . '.products-1'))->price(100)->build(),
                (new ProductBuilder($ids, $key . '.products-2'))->price(100)->build(),
            ],
        ];
    }

    private function transactional(\Closure $closure): void
    {
        $this->getContainer()->get(Connection::class)->beginTransaction();

        $closure();

        $this->getContainer()->get(Connection::class)->rollBack();
    }

    private function initBlogEntity(): ContainerInterface
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/custom-entity-test');

        $container = KernelLifecycleManager::bootKernel()->getContainer();

        // FYI: if this assertion fails, most likely because the app loading fails and the xml file is broken
        // the app system catch this error, and you didn't see any notice
        static::assertTrue($container->has('custom_entity_blog.repository'));

        return $container;
    }

    private function getSchema(): Schema
    {
        return $this->getContainer()
            ->get(Connection::class)
            ->createSchemaManager()
            ->introspectSchema();
    }

    private static function cleanUp(ContainerInterface $container): void
    {
        $container->get(Connection::class)->executeStatement('DELETE FROM category WHERE `type` = :type', ['type' => self::CATEGORY_TYPE]);

        try {
            $container->get(Connection::class)->executeStatement('DELETE FROM custom_entity_blog');
        } catch (TableNotFoundException) {
        }

        $container->get(Connection::class)->executeStatement('DELETE FROM product');
        $container->get(Connection::class)->executeStatement('DELETE FROM custom_entity');
        $container->get(Connection::class)->executeStatement('DELETE FROM product');

        $container->get(Connection::class)->executeStatement(
            'DELETE FROM app WHERE name IN (:name)',
            ['name' => ['custom-entity-test', 'store-api-custom-entity-test']],
            ['name' => ArrayParameterType::STRING]
        );

        $container->get(CustomEntitySchemaUpdater::class)->update();
    }

    private function testDefinition(ContainerInterface $container): void
    {
        $expected = [
            'category' => [
                (new FkField('custom_entity_blog_links_set_null_id', 'customEntityBlogLinksSetNullId', 'custom_entity_blog', 'id'))->addFlags(new Extension()),
                (new ManyToOneAssociationField('customEntityBlogLinksSetNull', 'custom_entity_blog_links_set_null_id', 'custom_entity_blog', 'id'))->addFlags(new Extension()),

                (new FkField('custom_entity_blog_links_restrict_id', 'customEntityBlogLinksRestrictId', 'custom_entity_blog', 'id'))->addFlags(new Extension()),
                (new ManyToOneAssociationField('customEntityBlogLinksRestrict', 'custom_entity_blog_links_restrict_id', 'custom_entity_blog', 'id'))->addFlags(new Extension()),
            ],
            'custom_entity_blog' => [
                (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                new DAL\FloatField('rating', 'rating'),
                new DAL\TranslatedField('title'),
                new DAL\TranslatedField('content'),
                new DAL\TranslatedField('display'),
                new DAL\JsonField('payload', 'payload'),
                new DAL\EmailField('email', 'email'),

                (new DAL\TranslationsAssociationField('custom_entity_blog_translation', 'custom_entity_blog_id', 'translations', 'id'))->addFlags(new Required()),

                (new DAL\ManyToManyAssociationField('products', 'product', 'custom_entity_blog_products', 'custom_entity_blog_id', 'product_id', 'id', 'id'))->addFlags(new DAL\Flag\CascadeDelete()),

                new DAL\OneToOneAssociationField('linkProductRestrict', 'link_product_restrict_id', 'id', 'product'),
                (new DAL\ReferenceVersionField('product', 'link_product_restrict_version_id'))->addFlags(new Required()),
                new FkField('link_product_restrict_id', 'linkProductRestrictId', 'product', 'id'),

                new DAL\OneToOneAssociationField('linkProductCascade', 'link_product_cascade_id', 'id', 'product'),
                (new DAL\ReferenceVersionField('product', 'link_product_cascade_version_id'))->addFlags(new Required()),
                new DAL\FkField('link_product_cascade_id', 'linkProductCascadeId', 'product', 'id'),

                new DAL\OneToOneAssociationField('linkProductSetNull', 'link_product_set_null_id', 'id', 'product'),
                (new DAL\ReferenceVersionField('product', 'link_product_set_null_version_id'))->addFlags(new Required()),
                new DAL\FkField('link_product_set_null_id', 'linkProductSetNullId', 'product', 'id'),

                new DAL\ManyToOneAssociationField('topSellerRestrict', 'top_seller_restrict_id', 'product', 'id'),
                (new DAL\ReferenceVersionField('product', 'top_seller_restrict_version_id'))->addFlags(new Required()),
                new DAL\FkField('top_seller_restrict_id', 'topSellerRestrictId', 'product', 'id'),

                new DAL\ManyToOneAssociationField('topSellerCascade', 'top_seller_cascade_id', 'product', 'id'),
                (new DAL\ReferenceVersionField('product', 'top_seller_cascade_version_id'))->addFlags(new Required()),
                (new DAL\FkField('top_seller_cascade_id', 'topSellerCascadeId', 'product', 'id'))->addFlags(new Required()),

                new DAL\ManyToOneAssociationField('topSellerSetNull', 'top_seller_set_null_id', 'product', 'id'),
                (new DAL\ReferenceVersionField('product', 'top_seller_set_null_version_id'))->addFlags(new Required()),
                new DAL\FkField('top_seller_set_null_id', 'topSellerSetNullId', 'product', 'id'),

                (new DAL\OneToManyAssociationField('linksRestrict', 'category', 'custom_entity_blog_links_restrict_id', 'id'))->addFlags(new DAL\Flag\RestrictDelete()),

                (new DAL\OneToManyAssociationField('linksSetNull', 'category', 'custom_entity_blog_links_set_null_id', 'id'))->addFlags(new DAL\Flag\SetNullOnDelete()),

                (new DAL\OneToManyAssociationField('comments', 'ce_blog_comment', 'custom_entity_blog_comments_id', 'id'))->addFlags(new DAL\Flag\CascadeDelete()),

                (new ManyToManyAssociationField('inheritedProducts', 'product', 'custom_entity_blog_inherited_products', 'custom_entity_blog_id', 'product_id', 'id', 'id'))->addFlags(new DAL\Flag\CascadeDelete(), new DAL\Flag\ReverseInherited('customEntityBlogInheritedProducts')),

                new FkField('inherited_top_seller_id', 'inheritedTopSellerId', 'product', 'id'),
                (new DAL\ManyToOneAssociationField('inheritedTopSeller', 'inherited_top_seller_id', 'product', 'id'))->addFlags(new DAL\Flag\ReverseInherited('customEntityBlogInheritedTopSeller')),
            ],
            'custom_entity_blog_translation' => [
                (new FkField('custom_entity_blog_id', 'customEntityBlogId', 'custom_entity_blog'))->addFlags(new Required(), new PrimaryKey()),
                (new FkField('language_id', 'languageId', 'language'))->addFlags(new Required(), new PrimaryKey()),
                (new DAL\StringField('title', 'title'))->addFlags(new Required()),
                (new DAL\LongTextField('content', 'content'))->addFlags(new DAL\Flag\AllowHtml()),
                new DAL\BoolField('display', 'display'),
            ],
            'product' => [
                (new ManyToManyAssociationField('customEntityBlogProducts', 'custom_entity_blog', 'custom_entity_blog_products', 'product_id', 'custom_entity_blog_id', 'id', 'id'))->addFlags(new DAL\Flag\CascadeDelete(), new Extension()),
                (new DAL\OneToOneAssociationField('customEntityBlogLinkProductRestrict', 'id', 'link_product_restrict_id', 'custom_entity_blog'))->addFlags(new DAL\Flag\RestrictDelete(), new Extension()),
                (new DAL\OneToOneAssociationField('customEntityBlogLinkProductCascade', 'id', 'link_product_cascade_id', 'custom_entity_blog'))->addFlags(new DAL\Flag\CascadeDelete(), new Extension()),
                (new DAL\OneToOneAssociationField('customEntityBlogLinkProductSetNull', 'id', 'link_product_set_null_id', 'custom_entity_blog'))->addFlags(new DAL\Flag\SetNullOnDelete(), new Extension()),
                (new OneToManyAssociationField('customEntityBlogTopSellerCascade', 'custom_entity_blog', 'top_seller_cascade_id'))->addFlags(new DAL\Flag\CascadeDelete(), new Extension()),
                (new OneToManyAssociationField('customEntityBlogTopSellerRestrict', 'custom_entity_blog', 'top_seller_restrict_id'))->addFlags(new DAL\Flag\RestrictDelete(), new Extension()),
                (new OneToManyAssociationField('customEntityBlogTopSellerSetNull', 'custom_entity_blog', 'top_seller_set_null_id'))->addFlags(new DAL\Flag\SetNullOnDelete(), new Extension()),
                (new ManyToManyAssociationField('customEntityBlogInheritedProducts', 'custom_entity_blog', 'custom_entity_blog_inherited_products', 'product_id', 'custom_entity_blog_id', 'id', 'id'))->addFlags(new DAL\Flag\CascadeDelete(), new Extension(), new DAL\Flag\Inherited()),
                (new OneToManyAssociationField('customEntityBlogInheritedTopSeller', 'custom_entity_blog', 'inherited_top_seller_id'))->addFlags(new DAL\Flag\SetNullOnDelete(), new DAL\Flag\Inherited(), new Extension()),
            ],
        ];

        foreach ($expected as $entity => $properties) {
            $definition = $container
                ->get(DefinitionInstanceRegistry::class)
                ->getByEntityName($entity);

            foreach ($properties as $field) {
                $field->compile($container->get(DefinitionInstanceRegistry::class));

                $name = $field->getPropertyName();
                $message = sprintf('Assertion for field "%s" in entity "%s" failed', $name, $entity);

                static::assertTrue($definition->getFields()->has($name), $message . ' - field not found');

                $actual = $definition->getFields()->get($name);
                static::assertInstanceOf(DAL\Field::class, $actual);
                static::assertInstanceOf($field::class, $actual, $message . ' - wrong class');

                foreach ($field->getFlags() as $flag) {
                    static::assertTrue($actual->is($flag::class), $message . ' - actual is not : ' . $flag::class);
                }

                foreach ($actual->getFlags() as $flag) {
                    static::assertTrue($field->is($flag::class), $message . ' - flag not expected: ' . $flag::class);
                }

                if ($field instanceof StorageAware) {
                    static::assertInstanceOf(StorageAware::class, $actual, $message);
                    static::assertSame($field->getStorageName(), $actual->getStorageName(), $message);
                }

                if ($field instanceof OneToManyAssociationField) {
                    static::assertInstanceOf(OneToManyAssociationField::class, $actual, $message);
                    static::assertSame($field->getReferenceField(), $actual->getReferenceField(), $message);
                    static::assertSame($field->getLocalField(), $actual->getLocalField(), $message);
                    static::assertSame($field->getAutoload(), $actual->getAutoload(), $message);
                    static::assertSame($field->getReferenceEntity(), $actual->getReferenceEntity(), $message);
                }

                if ($field instanceof ManyToManyAssociationField) {
                    static::assertInstanceOf(ManyToManyAssociationField::class, $actual, $message);
                    static::assertSame($field->getReferenceField(), $actual->getReferenceField(), $message);
                    static::assertSame($field->getLocalField(), $actual->getLocalField(), $message);
                    static::assertSame($field->getAutoload(), $actual->getAutoload(), $message);
                    static::assertSame($field->getMappingLocalColumn(), $actual->getMappingLocalColumn(), $message);
                    static::assertSame($field->getMappingReferenceColumn(), $actual->getMappingReferenceColumn(), $message);
                    static::assertSame($field->getReferenceEntity(), $actual->getReferenceEntity(), $message);
                }

                if ($field instanceof ManyToOneAssociationField) {
                    static::assertInstanceOf(ManyToOneAssociationField::class, $actual, $message);
                    static::assertSame($field->getReferenceField(), $actual->getReferenceField(), $message);
                    static::assertSame($field->getStorageName(), $actual->getStorageName(), $message);
                    static::assertSame($field->getAutoload(), $actual->getAutoload(), $message);
                    static::assertSame($field->getReferenceEntity(), $actual->getReferenceEntity(), $message);
                }
            }
        }
    }

    private function testAutoPermissions(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('name', 'custom-entity-test'));

        $app = $this->getContainer()->get('app.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(AppEntity::class, $app);

        $expected = [
            'tax:read',
            'product:read',
            'custom_entity_blog:read',
            'custom_entity_blog:create',
            'custom_entity_blog:update',
            'custom_entity_blog:delete',
            'ce_blog_comment:read',
            'ce_blog_comment:create',
            'ce_blog_comment:update',
            'ce_blog_comment:delete',
        ];

        static::assertInstanceOf(AclRoleEntity::class, $app->getAclRole());
        static::assertEquals($expected, $app->getAclRole()->getPrivileges());
    }

    private function testAllowDisable(bool $expected): void
    {
        $allowed = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT allow_disable FROM app WHERE name = :name', ['name' => 'custom-entity-test']);

        static::assertEquals($expected, (bool) $allowed);
    }
}
