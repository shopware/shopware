<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('sales-channel')]
class SalesChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelDefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var EntityRepository
     */
    private $apiRepository;

    /**
     * @var SalesChannelRepository
     */
    private $salesChannelProductRepository;

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->registry = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
        $this->apiRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelProductRepository = $this->getContainer()->get('sales_channel.product.repository');
        $this->factory = $this->getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testAssociationReplacement(): void
    {
        $fields = $this->getContainer()->get(SalesChannelProductDefinition::class)->getFields();

        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            $this->getContainer()->get(SalesChannelCategoryDefinition::class)->getClass(),
            $categories->getToManyReferenceDefinition()->getClass()
        );

        static::assertSame(
            $this->getContainer()->get(SalesChannelCategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );

        $fields = $this->getContainer()->get(ProductDefinition::class)->getFields();
        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(
            $this->getContainer()->get(CategoryDefinition::class),
            $categories->getToManyReferenceDefinition()
        );
    }

    public function testDefinitionRegistry(): void
    {
        static::assertSame(
            $this->getContainer()->get(SalesChannelProductDefinition::class),
            $this->registry->getByEntityName('product')
        );
    }

    public function testRepositoryCompilerPass(): void
    {
        static::assertInstanceOf(
            SalesChannelRepository::class,
            $this->getContainer()->get('sales_channel.product.repository')
        );
    }

    public function testLoadEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => 'test',
            'stock' => 10,
            'active' => true,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->apiRepository->create([$data], Context::createDefaultContext());

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'sales_channel.product.loaded', $listener);

        $context = $this->factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        $products = $this->salesChannelProductRepository->search($criteria, $context);

        static::assertCount(1, $products);

        /** @var SalesChannelProductEntity $product */
        $product = $products->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);

        static::assertCount(1, $product->getCategories());
    }
}
