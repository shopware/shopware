<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPromotedRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemPromotedRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const PAYLOAD_KEY = 'markAsTopseller';

    private LineItemPromotedRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPromotedRule();
    }

    /**
     * This test verifies that our name is not
     * touched without recognizing it.
     */
    public function testName(): void
    {
        static::assertSame('cartLineItemPromoted', $this->rule->getName());
    }

    /**
     * This test verifies that we have the correct constraint
     * and that no NotBlank is existing - only 1 BOOL constraint.
     * Otherwise a FALSE value would not work when saving in the administration.
     */
    public function testConstraints(): void
    {
        $expectedType = new Type(['type' => 'bool']);

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isPromoted', $ruleConstraints, 'Constraint isPromoted not found in Rule');
        static::assertCount(1, $ruleConstraints['isPromoted']);
        static::assertEquals($expectedType, $ruleConstraints['isPromoted'][0]);
    }

    public static function matchTestData(): array
    {
        return [
            [true, true, true],
            [true, false, false],
            [false, true, false],
            [false, false, true],
        ];
    }

    /**
     * This test verifies that our rule works correctly
     * when matching using a line item scope.
     *
     * @dataProvider matchTestData
     */
    public function testMatchScopeLineItem(bool $expected, bool $ruleValue, bool $itemValue): void
    {
        $this->rule->assign(['isPromoted' => $ruleValue]);

        $scope = new LineItemScope(
            $this->createLineItemWithTopsellerMarker($itemValue),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame($expected, $this->rule->match($scope));
    }

    /**
     * This test verifies that our rule works correctly
     * when matching using a cart rule scope.
     *
     * @dataProvider matchTestData
     */
    public function testMatchScopeCart(bool $expected, bool $ruleValue, bool $itemValue): void
    {
        $this->rule->assign(['isPromoted' => $ruleValue]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithTopsellerMarker($itemValue),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function testMatchScopeCartNested(): void
    {
        $this->rule->assign(['isPromoted' => true]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithTopsellerMarker(true),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    /**
     * This test verifies that we have a valid match
     * as soon as 1 item matches.
     */
    public function testSingleMatchRequiredInScopeCart(): void
    {
        $this->rule->assign(['isPromoted' => true]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithTopsellerMarker(false),
            $this->createLineItemWithTopsellerMarker(true),
            $this->createLineItemWithTopsellerMarker(false),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    /**
     * This test verifies that our rule returns FALSE
     * when validating with a line item that does not have a
     * valid entry in its payload.
     */
    public function testItemWithoutValidPayload(): void
    {
        $this->rule->assign(['isPromoted' => true]);

        $scope = new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertFalse($this->rule->match($scope));
    }

    private function createLineItemWithTopsellerMarker(bool $markAsTopseller): LineItem
    {
        return $this->createLineItem()->setPayloadValue(self::PAYLOAD_KEY, $markAsTopseller);
    }
}
