<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemOfManufacturerRule::class)]
class LineItemOfManufacturerRuleUnitTest extends TestCase
{
    public function testCustomProductOptionsDoNotMatchNotEqualManufacturerRule(): void
    {
        $manufacturerId = '019fa77183677a04ba9eaff57eed9627';

        $productLineItem = CartRuleFixture::createLineItem()
            ->setPayloadValue('manufacturerId', $manufacturerId);
        $optionLineItem = CartRuleFixture::createLineItem('customized-products-option')
            ->setChildren(new LineItemCollection([CartRuleFixture::createLineItem('option-values')]));

        $customizedProductLineItem = CartRuleFixture::createLineItem('customized-products')
            ->setGood(false)
            ->setChildren(new LineItemCollection([$productLineItem, $optionLineItem]));

        $rule = new LineItemOfManufacturerRule(
            Rule::OPERATOR_NEQ,
            [$manufacturerId],
        );

        $matches = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$customizedProductLineItem])),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertFalse($matches);
    }

    public function testProductWithoutManufacturerMatchesNotEqualManufacturerRule(): void
    {
        $rule = new LineItemOfManufacturerRule(Rule::OPERATOR_NEQ, [Uuid::randomHex()]);

        $productLineItem = CartRuleFixture::createLineItem()
            ->setPayloadValue('manufacturerId', null);

        $matches = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$productLineItem])),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertTrue($matches);
    }

    public function testPluginLineItemWithManufacturerMatchesEqualManufacturerRule(): void
    {
        $manufacturerId = Uuid::randomHex();

        $rule = new LineItemOfManufacturerRule(Rule::OPERATOR_EQ, [$manufacturerId]);

        $pluginLineItem = CartRuleFixture::createLineItem('my-plugin-item')
            ->setPayloadValue('manufacturerId', $manufacturerId);

        $matches = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$pluginLineItem])),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertTrue($matches);
    }

    #[DataProviderExternal(CartRuleFixture::class, 'lineItemWithoutProductDataProvider')]
    public function testLineItemWithoutProductData(string $type, bool $lineItemScope, bool $expected): void
    {
        $rule = new LineItemOfManufacturerRule(Rule::OPERATOR_NEQ, [Uuid::randomHex()]);

        $lineItem = CartRuleFixture::createLineItem($type);
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertSame($expected, $rule->match($scope));
    }
}
