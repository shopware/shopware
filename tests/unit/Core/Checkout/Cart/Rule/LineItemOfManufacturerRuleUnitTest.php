<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemOfManufacturerRule::class)]
class LineItemOfManufacturerRuleUnitTest extends TestCase
{
    use CartRuleHelperTrait;

    public function testCustomProductOptionsDoNotMatchNotEqualManufacturerRule(): void
    {
        $manufacturerId = '019fa77183677a04ba9eaff57eed9627';

        $productLineItem = self::createLineItem()
            ->setPayloadValue('manufacturerId', $manufacturerId);
        $optionLineItem = self::createLineItem('customized-products-option');

        $customizedProductLineItem = self::createLineItem('customized-products')
            ->setGood(false)
            ->setChildren(new LineItemCollection([$productLineItem, $optionLineItem]));

        $rule = new LineItemOfManufacturerRule(
            Rule::OPERATOR_NEQ,
            [$manufacturerId],
        );

        $matches = $rule->match(new CartRuleScope(
            self::createCart(new LineItemCollection([$customizedProductLineItem])),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertFalse($matches);
    }

    public function testCustomProductOptionDoesNotMatchNotEqualManufacturerRuleWithLineItemScope(): void
    {
        $manufacturerId = '019fa77183677a04ba9eaff57eed9627';

        $rule = new LineItemOfManufacturerRule(
            Rule::OPERATOR_NEQ,
            [$manufacturerId],
        );

        $hasMatch = $rule->match(new LineItemScope(
            self::createLineItem('customized-products-option'),
            static::createStub(SalesChannelContext::class),
        ));

        static::assertFalse($hasMatch);
    }
}
