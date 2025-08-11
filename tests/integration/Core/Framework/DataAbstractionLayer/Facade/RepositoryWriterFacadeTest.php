<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RepositoryWriterFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private RepositoryWriterFacadeHookFactory $factory;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(RepositoryWriterFacadeHookFactory::class);
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    public function testCreateViaUpsert(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $payload = [
            (new ProductBuilder($this->ids, 'p4'))
                ->visibility()
                ->price(300)
                ->build(),
        ];

        $facade->upsert('product', $payload);
        $createdProduct = $this->productRepository->search(new Criteria([$this->ids->get('p4')]), $this->context)->first();

        static::assertInstanceOf(ProductEntity::class, $createdProduct);
    }

    public function testUpdateViaUpsert(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $payload = [
            [
                'id' => $this->ids->get('p2'),
                'active' => true,
            ],
        ];

        $facade->upsert('product', $payload);
        $updated = $this->productRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->first();

        static::assertInstanceOf(ProductEntity::class, $updated);
        static::assertTrue($updated->getActive());
    }

    public function testDelete(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $facade->delete('product', [['id' => $this->ids->get('p2')]]);
        $deleted = $this->productRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->first();

        static::assertNull($deleted);
    }

    public function testSync(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $facade = $this->factory->factory(
            new TestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );

        $facade->sync([
            [
                'entity' => 'product',
                'action' => 'upsert',
                'payload' => [
                    (new ProductBuilder($this->ids, 'p4'))
                        ->visibility()
                        ->price(300)
                        ->build(),
                    [
                        'id' => $this->ids->get('p2'),
                        'active' => true,
                    ],
                ],
            ],
            [
                'entity' => 'product',
                'action' => 'delete',
                'payload' => [
                    ['id' => $this->ids->get('p3')],
                ],
            ],
        ]);

        $this->productRepository = static::getContainer()->get('product.repository');

        $createdProduct = $this->productRepository->search(new Criteria([$this->ids->get('p4')]), $this->context)->first();
        static::assertInstanceOf(ProductEntity::class, $createdProduct);

        $updated = $this->productRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->first();
        static::assertInstanceOf(ProductEntity::class, $updated);
        static::assertTrue($updated->getActive());

        $deleted = $this->productRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->first();
        static::assertNull($deleted);
    }

    public function testUpsertWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->upsert('product', [
            [
                'id' => $this->ids->get('p2'),
                'active' => true,
            ],
        ]);
    }

    public function testDeleteWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->upsert('product', [['id' => $this->ids->get('p3')]]);
    }

    public function testSyncWithoutPermission(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->sync([
            [
                'entity' => 'product',
                'action' => 'delete',
                'payload' => [['id' => $this->ids->get('p3')]],
            ],
        ]);
    }

    public function testIntegrationCreateCase(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-create',
            $this->context,
            [],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $taxRepository = static::getContainer()->get('tax.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'new tax'));

        $createdTax = $taxRepository->search($criteria, $this->context)->first();
        static::assertInstanceOf(TaxEntity::class, $createdTax);
    }

    public function testIntegrationUpdateCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-update',
            $this->context,
            [
                'productId' => $this->ids->get('p2'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $updated = $this->productRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->first();
        static::assertInstanceOf(ProductEntity::class, $updated);
        static::assertTrue($updated->getActive());
    }

    public function testIntegrationDeleteCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-delete',
            $this->context,
            [
                'productId' => $this->ids->get('p3'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $deleted = $this->productRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->first();
        static::assertNull($deleted);
    }

    public function testIntegrationSyncCase(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $hook = new TestHook(
            'writer-sync',
            $this->context,
            [
                'updateProductId' => $this->ids->get('p2'),
                'deleteProductId' => $this->ids->get('p3'),
            ],
            [
                RepositoryWriterFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $updated = $this->productRepository->search(new Criteria([$this->ids->get('p2')]), $this->context)->first();
        static::assertInstanceOf(ProductEntity::class, $updated);
        static::assertTrue($updated->getActive());

        $deleted = $this->productRepository->search(new Criteria([$this->ids->get('p3')]), $this->context)->first();
        static::assertNull($deleted);
    }

    private function createProducts(): void
    {
        $taxId = $this->getExistingTaxId();
        $this->ids->set('t1', $taxId);

        $product1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->visibility()
            ->manufacturer('m1')
            ->variant(
                (new ProductBuilder($this->ids, 'v1.1'))
                    ->build()
            );

        $product2 = (new ProductBuilder($this->ids, 'p2'))
            ->price(200)
            ->visibility()
            ->active(false);

        $product3 = (new ProductBuilder($this->ids, 'p3'))
            ->visibility()
            ->price(300);

        static::getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], $this->context);
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($app);

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getIntegrationId()
        );
    }

    private function getExistingTaxId(): string
    {
        /** @var EntityRepository $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Standard rate'));

        $taxId = $taxRepository->searchIds($criteria, $this->context)->firstId();

        static::assertNotNull($taxId);

        return $taxId;
    }
}
