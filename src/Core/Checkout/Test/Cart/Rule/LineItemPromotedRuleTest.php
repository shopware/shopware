<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPromotedRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @group rules
 */
class LineItemPromotedRuleTest extends TestCase
{
    private const PAYLOAD_KEY = 'markAsTopseller';

    /**
     * @var LineItemPromotedRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPromotedRule();
    }

    /**
     * This test verifies that our name is not
     * touched without recognizing it.
     *
     * @group rules
     */
    public function testName(): void
    {
        static::assertEquals('cartLineItemPromoted', $this->rule->getName());
    }

    /**
     * This test verifies that we have the correct constraint
     * and that no NotBlank is existing - only 1 BOOL constraint.
     * Otherwise a FALSE value would not work when saving in the administration.
     *
     * @group rules
     */
    public function testConstraints(): void
    {
        $expectedType = new Type(['type' => 'bool']);

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isPromoted', $ruleConstraints, 'Constraint isPromoted not found in Rule');
        static::assertCount(1, $ruleConstraints['isPromoted']);
        static::assertEquals($expectedType, $ruleConstraints['isPromoted'][0]);
    }

    /**
     * This test verifies that our rule works correctly
     * when matching using a line item scope.
     *
     * @testWith        [true, true, true]
     *                  [true, false, false]
     *                  [false, true, false]
     *                  [false, false, true]
     */
    public function testMatchScopeLineItem(bool $expected, bool $ruleValue, bool $itemValue): void
    {
        $scope = new LineItemScope(
            $this->createLineItem($itemValue),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['isPromoted' => $ruleValue]);

        $isMatching = $this->rule->match($scope);

        static::assertEquals($expected, $isMatching);
    }

    /**
     * This test verifies that our rule works correctly
     * when matching using a cart rule scope.
     *
     * @testWith        [true, true, true]
     *                  [true, false, false]
     *                  [false, true, false]
     *                  [false, false, true]
     */
    public function testMatchScopeCart(bool $expected, bool $ruleValue, bool $itemValue): void
    {
        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($itemValue));

        $cart = new Cart('test', Uuid::randomHex());
        $cart->setLineItems($lineItemCollection);

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $this->rule->assign(['isPromoted' => $ruleValue]);

        $isMatching = $this->rule->match($scope);

        static::assertEquals($expected, $isMatching);
    }

    /**
     * This test verifies that we have a valid match
     * as soon as 1 item matches.
     */
    public function testSingleMatchRequiredInScopeCart(): void
    {
        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem(false));
        $lineItemCollection->add($this->createLineItem(true));
        $lineItemCollection->add($this->createLineItem(false));

        $cart = new Cart('test', Uuid::randomHex());
        $cart->setLineItems($lineItemCollection);

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $this->rule->assign(['isPromoted' => true]);

        $isMatching = $this->rule->match($scope);

        static::assertTrue($isMatching);
    }

    /**
     * This test verifies that our rule returns FALSE
     * when validating with a line item that does not have a
     * valid entry in its payload.
     */
    public function testItemWithoutValidPayload(): void
    {
        $scope = new LineItemScope(
            new LineItem('dummy-article', 'product', null, 3),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['isPromoted' => true]);

        $isMatching = $this->rule->match($scope);

        static::assertFalse($isMatching);
    }

    private function createLineItem(bool $markAsTopseller): LineItem
    {
        $item = (new LineItem(Uuid::randomHex(), 'product', null, 3));
        $item->setPayloadValue(self::PAYLOAD_KEY, $markAsTopseller);

        return $item;
    }
}
