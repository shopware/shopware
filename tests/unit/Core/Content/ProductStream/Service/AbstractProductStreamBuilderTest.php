<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AbstractProductStreamBuilder::class)]
#[DisabledFeatures(['v6.9.0.0'])]
class AbstractProductStreamBuilderTest extends TestCase
{
    use EnvTestBehaviour;

    public function testForwardsLegacyBuildFiltersCallToEnrichCriteriaWithThe68FlagActive(): void
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

        static::assertSame([$filter], $builder->buildFilters('stream-id', Context::createDefaultContext()));
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the legacy buildFilters() bridge.
     */
    public function testLegacyBuildFiltersCallThrowsWithThe69FlagActive(): void
    {
        Feature::registerFeature('v6.8.0.0', ['default' => true]);
        Feature::registerFeature('v6.9.0.0', ['default' => false]);
        $this->setEnvVars(['V6_9_0_0' => '1']);
        $builder = new class extends AbstractProductStreamBuilder {
            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
            }
        };

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
            AbstractProductStreamBuilder::class,
            AbstractProductStreamBuilder::class . '::buildFilters',
            'v6.9.0.0',
            AbstractProductStreamBuilder::class . '::enrichCriteria',
        )));

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }
}
