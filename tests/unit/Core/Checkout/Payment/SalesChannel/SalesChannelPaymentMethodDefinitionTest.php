<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\SalesChannel\SalesChannelPaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelPaymentMethodDefinition::class)]
class SalesChannelPaymentMethodDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelPaymentMethodDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('payment_method.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}
