<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\SalesChannel\SalesChannelShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelShippingMethodDefinition::class)]
class SalesChannelShippingMethodDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelShippingMethodDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
