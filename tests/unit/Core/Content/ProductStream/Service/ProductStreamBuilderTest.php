<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ProductStreamBuilder::class)]
class ProductStreamBuilderTest extends TestCase
{
    /**
     * buildFilters() is the deprecated backward-compatible fallback. It carries the deprecation itself,
     * so under the v6.8.0.0 flag it throws — that is the hard-fail that forces interface-only builders
     * (which still route through this method) to migrate to AbstractProductStreamBuilder::enrichCriteria().
     */
    public function testBuildFiltersThrowsWhenV68IsActive(): void
    {
        /** @var EntityRepository<ProductStreamCollection>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $builder = new ProductStreamBuilder($repository, $this->createMock(EntityDefinition::class));

        $this->expectException(FeatureException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }

    /**
     * Before v6.8.0.0 the deprecated buildFilters() stays callable: it only triggers a non-fatal deprecation
     * (the emitted message is asserted in the integration test). It must not throw the FeatureException here —
     * it proceeds past the deprecation gate and fails only because the stub stream does not resolve.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildFiltersDoesNotThrowWhenV68IsInactive(): void
    {
        /** @var StaticEntityRepository<ProductStreamCollection> $repository */
        $repository = new StaticEntityRepository([new ProductStreamCollection([])]);
        $builder = new ProductStreamBuilder($repository, $this->createMock(EntityDefinition::class));

        $this->expectException(EntityNotFoundException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }
}
