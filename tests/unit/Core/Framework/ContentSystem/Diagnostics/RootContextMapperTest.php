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
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RootContextMapper::class)]
class RootContextMapperTest extends TestCase
{
    /**
     * The two fields that make the entry root-ambient are asserted beside the mapped ones: the flag is what
     * marks it, and the absent provider element id is the other half of that. Both are pinned, because the
     * virtual-root sentinel this used to write is not a provider address, and a mutation of either field
     * alone would otherwise pass.
     */
    #[TestDox('maps a page requirement to a broadcast single root context with the loader-resolved FQCN')]
    public function testMapsRequirementToRootContext(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(SalesChannelProductEntity::class);

        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $contexts = (new RootContextMapper($provider))->map([$requirement]);

        static::assertCount(1, $contexts);
        static::assertSame('product', $contexts[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $contexts[0]->fqcn);
        static::assertSame(ContextType::Single, $contexts[0]->contextType);
        static::assertSame(DistributionStrategy::Broadcast, $contexts[0]->distribution);
        static::assertTrue($contexts[0]->root);
        static::assertNull($contexts[0]->providerElementId);
    }

    #[TestDox('maps an empty requirement set to no root context')]
    public function testEmptyRequirementsMapToEmptyRootContext(): void
    {
        $provider = static::createStub(DataLoaderProvider::class);

        static::assertSame([], (new RootContextMapper($provider))->map([]));
    }

    #[TestDox('propagates an unknown-entity exception without swallowing it')]
    public function testResolveTypePropagatesException(): void
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('prodct'));

        (new RootContextMapper($provider))->resolveType($requirement);
    }
}
