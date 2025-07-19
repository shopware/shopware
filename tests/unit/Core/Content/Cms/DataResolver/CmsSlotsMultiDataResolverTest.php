<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Fixtures\MultiCmsElementResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsMultiDataResolverTest extends TestCase
{
    private DefinitionInstanceRegistry $registry;

    private ExtensionDispatcher $extensions;

    protected function setUp(): void
    {
        $this->registry = new DefinitionInstanceRegistry(new ContainerBuilder(), [], []);
        $dispatcher = $this->createMock(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($dispatcher);
    }

    public function testMultipleDefinitionCriteriaWithSameHashResolveCorrectly(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'first',
                'type' => 'first',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-2',
                'slot' => 'second',
                'type' => 'second',
            ]),
        ]);

        $firstResolver = $this->createResolver('first', ProductDefinition::class);
        $secondResolver = $this->createResolver('second', CategoryDefinition::class);

        $productDefinition = new ProductDefinition();
        $categoryDefinition = new CategoryDefinition();
        $this->registry->register($productDefinition);
        $this->registry->register($categoryDefinition);

        $productRepository = $this->createRepositoryMock($productDefinition);
        $categoryRepository = $this->createRepositoryMock($categoryDefinition);

        $resolver = new CmsSlotsDataResolver(
            [$firstResolver, $secondResolver],
            ['product' => $productRepository, 'category' => $categoryRepository],
            $this->registry,
            $this->extensions
        );

        $context = Generator::generateSalesChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $resolver->resolve($slots, $resolverContext);

        /** @var CmsSlotEntity $firstSlot */
        $firstSlot = $slots->getSlot('first');
        /** @var EntitySearchResult<ProductCollection> $firstData */
        $firstData = $firstSlot->getData();
        static::assertInstanceOf(EntitySearchResult::class, $firstData);
        static::assertInstanceOf(ProductCollection::class, $firstData->getEntities());

        /** @var CmsSlotEntity $secondSlot */
        $secondSlot = $slots->getSlot('second');
        /** @var EntitySearchResult<ProductCollection> $secondData */
        $secondData = $secondSlot->getData();
        static::assertInstanceOf(EntitySearchResult::class, $secondData);
        static::assertInstanceOf(CategoryCollection::class, $secondData->getEntities());
    }

    private function createResolver(string $type, string $definition): MultiCmsElementResolver
    {
        return new MultiCmsElementResolver($type, $definition);
    }

    private function createRepositoryMock(EntityDefinition $definition): SalesChannelRepository
    {
        $repository = static::createStub(SalesChannelRepository::class);
        $collection = new ($definition->getCollectionClass())();
        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);
        $repository->method('search')->willReturn($result);

        return $repository;
    }
}
