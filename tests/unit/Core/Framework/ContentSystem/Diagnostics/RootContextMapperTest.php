<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;

/**
 * @internal
 */
#[CoversClass(RootContextMapper::class)]
class RootContextMapperTest extends TestCase
{
    #[TestDox('maps a page requirement to a broadcast single root context with the loader-resolved FQCN')]
    public function testMapsRequirementToRootContext(): void
    {
        $requirement = new DataRequirement('product', 'entity', $this->createMock(AbstractContentDataLoaderConfig::class));

        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(SalesChannelProductEntity::class);

        $provider = $this->createMock(DataLoaderProvider::class);
        $provider->method('get')->with('entity')->willReturn($loader);

        $contexts = (new RootContextMapper($provider))->map([$requirement]);

        static::assertCount(1, $contexts);
        static::assertSame('product', $contexts[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $contexts[0]->fqcn);
        static::assertSame(ContextType::Single, $contexts[0]->contextType);
        static::assertSame(DistributionStrategy::Broadcast, $contexts[0]->distribution);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $contexts[0]->providerElementId);
    }

    #[TestDox('an empty requirement set (header/footer) maps to no root context')]
    public function testEmptyRequirementsMapToEmptyRootContext(): void
    {
        $provider = $this->createMock(DataLoaderProvider::class);

        static::assertSame([], (new RootContextMapper($provider))->map([]));
    }

    #[TestDox('resolveType propagates an unknown-entity exception rather than swallowing it')]
    public function testResolveTypePropagatesException(): void
    {
        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $provider = $this->createMock(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $requirement = new DataRequirement('product', 'entity', $this->createMock(AbstractContentDataLoaderConfig::class));

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/unknown entity "prodct"/');

        (new RootContextMapper($provider))->resolveType($requirement);
    }
}
