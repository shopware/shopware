<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(SalesChannelCurrencyDefinition::class)]
class SalesChannelCurrencyDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCurrencyDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('currency.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
