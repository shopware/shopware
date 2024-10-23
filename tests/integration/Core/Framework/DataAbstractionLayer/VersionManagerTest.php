<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ManyToOneProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToOneProductExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(VersionManager::class)]
class VersionManagerTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use IntegrationTestBehaviour;

    private const PRODUCT_ID = 'product-1';

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private VersionManager $versionManager;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->versionManager = $this->getContainer()->get(VersionManager::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->registerEntityDefinitionAndInitDatabase();
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `product`
            DROP FOREIGN KEY `fk.product.many_to_one_id`;
        ');
        $this->connection->executeStatement('DROP TABLE `many_to_one_product`');
        $this->connection->executeStatement('
            ALTER TABLE `product`
            DROP COLUMN `many_to_one_id`
        ');
        $this->connection->beginTransaction();

        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
    }

    public function testWhenAddAnExtensionWithFKIdThenFKIdShouldBeCloned(): void
    {
        $extendableId = Uuid::randomHex();
        $product = (new ProductBuilder($this->ids, self::PRODUCT_ID))->stock(1)
            ->name('Test Product')->price(1000)->build();

        $product['manyToOne'] = [
            'id' => $extendableId,
        ];
        $productId = $product['id'];
        $this->productRepository->create([$product], $this->context);

        $criteria = (new Criteria([$productId]))->addAssociation('manyToOne');

        $product = $this->productRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($product);

        static::assertTrue($product->hasExtension('manyToOne'));
        $extension = $product->getExtension('manyToOne');

        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertEquals($extendableId, $extension->get('id'));

        $criteria = (new Criteria())->addFilter(new EqualsFilter('manyToOne.id', $extendableId));

        $products = $this->productRepository->searchIds($criteria, $this->context);
        static::assertTrue($products->has($productId));

        $clonedAffected = $this->getClone($productId);

        $clonedProduct = $clonedAffected['product'][0];
        static::assertInstanceOf(EntityWriteResult::class, $clonedProduct);

        $clonedProductId = $clonedProduct->getPayload()['id'];
        $clonedManyToOneId = $clonedProduct->getPayload()['manyToOneId'];
        static::assertNotEmpty($clonedProductId);
        static::assertSame($extendableId, $clonedManyToOneId);
    }

    public function testContextScopeAvailableDuringMerge(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'p1'))->price(100)->build();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('product.repository')->create([$product], $context);

        $versionId = $this->getContainer()->get('product.repository')
            ->createVersion($ids->get('p1'), $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->getContainer()->get('product.repository')
            ->update([['id' => $ids->get('p1'), 'name' => 'test']], $versionContext);

        // now ensure that we get a validate event for the merge request
        $called = false;

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            PreWriteValidationEvent::class,
            function (PreWriteValidationEvent $event) use (&$called): void {
                // we also get a validation event for the version tables
                if (!$event->getPrimaryKeys('product')) {
                    return;
                }

                $called = true;
                // some validators depend on that to disable insert/update validation for merge requests
                static::assertTrue($event->getWriteContext()->hasState(VersionManager::MERGE_SCOPE));
            }
        );

        $this->getContainer()->get('product.repository')->merge($versionId, $context);

        static::assertTrue($called);
    }

    public function testWhenNotAddingFKThenItShouldNotBeAvailable(): void
    {
        $product = (new ProductBuilder($this->ids, self::PRODUCT_ID))->stock(1)
            ->name('Test Product')->price(1000)->build();

        $this->productRepository->create([$product], $this->context);
        $criteria = (new Criteria([$product['id']]))->addAssociation('manyToOne');

        $product = $this->productRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($product);

        $extension = $product->getExtension('manyToOne');
        static::assertEmpty($extension);

        $clonedAffected = $this->getClone($product->getId());

        $clonedProduct = $clonedAffected['product'][0];
        static::assertInstanceOf(EntityWriteResult::class, $clonedProduct);
        $clonedManyToOne = $clonedProduct->getPayload();
        static::assertArrayNotHasKey('manyToOneId', $clonedManyToOne);
    }

    private function registerEntityDefinitionAndInitDatabase(): void
    {
        $this->registerDefinition(ManyToOneProductDefinition::class);

        $this->registerDefinitionWithExtensions(
            ProductDefinition::class,
            ToOneProductExtension::class
        );
        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `many_to_one_product`;
            CREATE TABLE `many_to_one_product` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
        ');

        $this->connection->executeStatement('
            ALTER TABLE `product`
                ADD COLUMN `many_to_one_id` binary(16) NULL,
                ADD CONSTRAINT `fk.product.many_to_one_id` FOREIGN KEY (`many_to_one_id`)
                REFERENCES `many_to_one_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $this->connection->beginTransaction();
    }

    /**
     * @return array<string, array<EntityWriteResult>>
     */
    private function getClone(string $productId): array
    {
        return $this->versionManager->clone(
            $this->getContainer()->get(ProductDefinition::class),
            $productId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            WriteContext::createFromContext($this->context),
            new CloneBehavior()
        );
    }
}
