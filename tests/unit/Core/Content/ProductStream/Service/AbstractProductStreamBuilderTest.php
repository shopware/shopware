<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AbstractProductStreamBuilder::class)]
class AbstractProductStreamBuilderTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testForwardsLegacyBuildFiltersCallToEnrichCriteria(): void
    {
        $filter = new EqualsFilter('active', true);
        $builder = new class($filter) extends AbstractProductStreamBuilder {
            public function __construct(private readonly Filter $filter)
            {
            }

            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
                $criteria->addFilter($this->filter);
            }
        };

        static::assertTrue((new \ReflectionClass(AbstractProductStreamBuilder::class))->implementsInterface(ProductStreamBuilderInterface::class));
        static::assertSame([$filter], $builder->buildFilters('stream-id', Context::createDefaultContext()));
    }

    public function testLegacyBuildFiltersCallThrowsWhenV68IsActive(): void
    {
        $builder = new class extends AbstractProductStreamBuilder {
            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
            }
        };

        $this->expectException(FeatureException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }
}
