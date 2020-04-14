<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @group rules
 */
class LineItemReleaseDateRuleTest extends TestCase
{
    private const PAYLOAD_KEY = 'releaseDate';

    /**
     * @var LineItemReleaseDateRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemReleaseDateRule();
    }

    /**
     * @group rules
     */
    public function testName(): void
    {
        static::assertEquals('cartLineItemReleaseDate', $this->rule->getName());
    }

    /**
     * This test verifies that we have 2 constraints.
     * One for the date and one for the operators.
     *
     * @group rules
     */
    public function testConstraints(): void
    {
        $expectedOperators = [
            LineItemCreationDateRule::OPERATOR_NEQ,
            LineItemCreationDateRule::OPERATOR_GTE,
            LineItemCreationDateRule::OPERATOR_LTE,
            LineItemCreationDateRule::OPERATOR_EQ,
            LineItemCreationDateRule::OPERATOR_GT,
            LineItemCreationDateRule::OPERATOR_LT,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lineItemReleaseDate', $ruleConstraints, 'Constraint lineItemReleaseDate not found in Rule');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');

        $date = $ruleConstraints['lineItemReleaseDate'];
        $operators = $ruleConstraints['operator'];

        static::assertEquals(new NotBlank(), $date[0]);
        static::assertEquals(new Type(['type' => 'string']), $date[1]);

        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);
    }

    public function getMatchValues(): array
    {
        return [
            'EQ - positive 1' => [true, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_EQ],
            'EQ - positive 2' => [true, '2020-02-06', '2020-02-06', LineItemCreationDateRule::OPERATOR_EQ],
            'EQ - negative' => [false, '2020-02-05 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_EQ],
            'NEQ - positive 1' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_NEQ],
            'NEQ - positive 2' => [true, '2020-02-05', '2020-02-06', LineItemCreationDateRule::OPERATOR_NEQ],
            'NEQ - negative' => [false, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_NEQ],
            'GT - positive' => [true, '2020-02-07 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_GT],
            'GT - negative' => [false, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_GT],
            'GTE - positive 1' => [true, '2020-02-07 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_GTE],
            'GTE - positive 2' => [true, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_GTE],
            'GTE - negative' => [false, '2020-02-05 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_GTE],
            'LT - positive' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_LT],
            'LT - negative' => [false, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_LT],
            'LTE - positive 1' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_LTE],
            'LTE - positive 2' => [true, '2020-02-06 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_LTE],
            'LTE - negative' => [false, '2020-02-07 00:00:00', '2020-02-06 02:00:00', LineItemCreationDateRule::OPERATOR_LTE],
        ];
    }

    /**
     * This test verifies that our rule works correctly
     * with all the different operators and values.
     *
     * @group rules
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(bool $expected, string $itemReleased, string $ruleDate, string $operator): void
    {
        $lineItem = $this->createLineItem($itemReleased);

        $scope = new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lineItemReleaseDate' => $ruleDate, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertEquals($expected, $isMatching);
    }

    /**
     * @group rules
     */
    public function testItemWithoutReleaseDateIsFalse(): void
    {
        $scope = new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        );

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    /**
     * @group rules
     */
    public function testInvalidDateValueIsFalse(): void
    {
        $scope = new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lineItemReleaseDate' => 'invalid-date-value', 'operator' => LineItemCreationDateRule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    /**
     * @group rules
     */
    public function testInvalidScope(): void
    {
        $scope = (new CheckoutRuleScope(
            $this->createMock(SalesChannelContext::class)
        ));

        $this->rule->assign(['lineItemReleaseDate' => '2020-02-06 00:00:00', 'operator' => LineItemCreationDateRule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    /**
     * @group rules
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScope(string $ruleCreationDate, string $lineItemReleaseDate1, string $lineItemReleaseDate2, bool $expected): void
    {
        $this->rule->assign(['lineItemReleaseDate' => $ruleCreationDate, 'operator' => LineItemCreationDateRule::OPERATOR_EQ]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($lineItemReleaseDate1));
        $lineItemCollection->add($this->createLineItem($lineItemReleaseDate2));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match((new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        )));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'no match' => ['2020-02-06 00:00:00', '2020-01-01 12:30:00', '2020-01-01 18:00:00', false],
            'one matching' => ['2020-02-06 00:00:00', '2020-02-06 12:30:00', '2020-01-01 18:00:00', true],
            'all matching' => ['2020-02-06 00:00:00', '2020-02-06 12:30:00', '2020-02-06 18:00:00', true],
        ];
    }

    private function createLineItem(string $releaseDate): LineItem
    {
        $item = new LineItem(Uuid::randomHex(), 'product', null, 3);
        $item->setPayloadValue(self::PAYLOAD_KEY, $releaseDate);

        return $item;
    }
}
