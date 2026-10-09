<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\MainCategory\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\MainCategory\SalesChannel\SalesChannelMainCategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SalesChannelMainCategoryDefinition::class)]
class SalesChannelMainCategoryDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelMainCategoryDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('salesChannelId', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
