<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ManyToOneProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToOneProductExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\VersionManager
 */
class VersionManagerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    private const PRODUCT_ID = 'product-1';

    private Connection $connection;

    private EntityRepository $productRepository;

    private VersionManager $versionManager;

    private Context $context;

    private TestDataCollection $ids;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->versionManager = $this->getContainer()->get(VersionManager::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->registerEntityDefinitionAndInitDatabase();
        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection();
    }

    protected function tearDown(): void
    {
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
        $this->removeExtension(ToOneProductExtension::class);
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
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $this->context)->first();
        static::assertNotNull($product);

        static::assertTrue($product->hasExtension('manyToOne'));
        /** @var ArrayEntity $extension */
        $extension = $product->getExtension('manyToOne');

        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertEquals($extendableId, $extension->get('id'));

        $criteria = (new Criteria())->addFilter(new EqualsFilter('manyToOne.id', $extendableId));

        $products = $this->productRepository->searchIds($criteria, $this->context);
        static::assertTrue($products->has($productId));

        /** @var array<string, array<int, mixed>> $clonedAffected */
        $clonedAffected = $this->versionManager->clone(
            $this->getContainer()->get(ProductDefinition::class),
            $productId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            WriteContext::createFromContext($this->context),
            new CloneBehavior()
        );

        $clonedProductId = $clonedAffected['product'][0]->getPayload()['id'];
        $clonedManyToOneId = $clonedAffected['product'][0]->getPayload()['manyToOneId'];
        static::assertNotEmpty($clonedProductId);
        static::assertSame($extendableId, $clonedManyToOneId);
    }

    public function testWhenNotAddingFKThenItShouldNotBeAvailable(): void
    {
        $product = (new ProductBuilder($this->ids, self::PRODUCT_ID))->stock(1)
            ->name('Test Product')->price(1000)->build();

        $this->productRepository->create([$product], $this->context);
        $criteria = (new Criteria([$product['id']]))->addAssociation('manyToOne');

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $this->context)->first();
        static::assertNotNull($product);

        /** @var ArrayEntity|null $extension */
        $extension = $product->getExtension('manyToOne');
        static::assertEmpty($extension);

        /** @var array<string, array<int, mixed>> $clonedAffected */
        $clonedAffected = $this->versionManager->clone(
            $this->getContainer()->get(ProductDefinition::class),
            $product->getId(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            WriteContext::createFromContext($this->context),
            new CloneBehavior()
        );

        $clonedManyToOne = $clonedAffected['product'][0]->getPayload();
        static::assertArrayNotHasKey('manyToOneId', $clonedManyToOne, );
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
}
