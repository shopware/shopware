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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

class SalesChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelDefinitionRegistry
     */
    private $registry;

    /**
     * @var EntityRepositoryInterface
     */
    private $apiRepository;

    /**
     * @var SalesChannelRepository
     */
    private $salesChannelProductRepository;

    /**
     * @var SalesChannelContextFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->registry = $this->getContainer()->get(SalesChannelDefinitionRegistry::class);
        $this->apiRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelProductRepository = $this->getContainer()->get('sales_channel.product.repository');
        $this->factory = $this->getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testAssociationReplacement()
    {
        $fields = SalesChannelProductDefinition::getFields();

        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(SalesChannelCategoryDefinition::class, $categories->getReferenceDefinition());

        $fields = ProductDefinition::getFields();
        $categories = $fields->get('categories');

        /** @var ManyToManyAssociationField $categories */
        static::assertSame(CategoryDefinition::class, $categories->getReferenceDefinition());
    }

    public function testDefinitionRegistry()
    {
        static::assertSame(SalesChannelProductDefinition::class, $this->registry->get('product'));
    }

    public function testRepositoryCompilerPass()
    {
        static::assertInstanceOf(
            SalesChannelRepository::class,
            $this->getContainer()->get('sales_channel.product.repository')
        );
    }

    public function testLoadEntities()
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => 'test',
            'stock' => 10,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->apiRepository->create([$data], Context::createDefaultContext());

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('sales_channel.product.loaded', $listener);

        $context = $this->factory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

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

class CallableClass
{
    public function __invoke()
    {
    }
}
