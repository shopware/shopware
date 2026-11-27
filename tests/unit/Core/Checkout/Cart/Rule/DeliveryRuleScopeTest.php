<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DeliveryRuleScope::class)]
class DeliveryRuleScopeTest extends TestCase
{
    public function testScopeExposesDeliveryCartAndContext(): void
    {
        $cart = new Cart('test');
        $context = Generator::generateSalesChannelContext();
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $scope = new DeliveryRuleScope($delivery, $cart, $context);

        static::assertSame($delivery, $scope->getDelivery());
        static::assertSame($cart, $scope->getCart());
        static::assertSame($context, $scope->getSalesChannelContext());
        static::assertSame($context->getContext(), $scope->getContext());
    }
}
