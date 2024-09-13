<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutRuleScope::class)]
class CheckoutRuleScopeTest extends TestCase
{
    public function testConstruct(): void
    {
        $scope = new CheckoutRuleScope($context = Generator::createSalesChannelContext());

        static::assertSame($context, $scope->getSalesChannelContext());
        static::assertSame($context->getContext(), $scope->getContext());
    }
}
