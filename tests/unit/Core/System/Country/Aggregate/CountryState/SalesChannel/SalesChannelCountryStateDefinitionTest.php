<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\Aggregate\CountryState\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\SalesChannel\SalesChannelCountryStateDefinition;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelCountryStateDefinition::class)]
class SalesChannelCountryStateDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCountryStateDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('country_state.country.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
