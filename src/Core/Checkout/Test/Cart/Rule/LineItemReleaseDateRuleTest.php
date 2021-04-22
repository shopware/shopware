<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @group rules
 */
class LineItemReleaseDateRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const PAYLOAD_KEY = 'releaseDate';

    private LineItemReleaseDateRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemReleaseDateRule();
    }

    public function testName(): void
    {
        static::assertSame('cartLineItemReleaseDate', $this->rule->getName());
    }

    /**
     * This test verifies that we have 2 constraints.
     * One for the date and one for the operators.
     */
    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
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
            'EQ - positive 1' => [true, '2020-02-06 02:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_EQ],
            'EQ - positive 2' => [true, '2020-02-06', '2020-02-06', Rule::OPERATOR_EQ],
            'EQ - negative' => [false, '2020-02-05 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_EQ],
            'NEQ - positive 1' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_NEQ],
            'NEQ - positive 2' => [true, '2020-02-05', '2020-02-06', Rule::OPERATOR_NEQ],
            'NEQ - negative' => [false, '2020-02-06 00:00:00', '2020-02-06 00:00:00', Rule::OPERATOR_NEQ],
            'GT - positive' => [true, '2020-02-07 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_GT],
            'GT - negative' => [false, '2020-02-06 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_GT],
            'GTE - positive 1' => [true, '2020-02-07 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_GTE],
            'GTE - positive 2' => [true, '2020-02-06 02:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_GTE],
            'GTE - negative' => [false, '2020-02-05 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_GTE],
            'LT - positive' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LT],
            'LT - negative' => [false, '2020-02-06 03:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LT],
            'LTE - positive 1' => [true, '2020-02-05 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LTE],
            'LTE - positive 2' => [true, '2020-02-06 02:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LTE],
            'LTE - negative' => [false, '2020-02-07 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LTE],
            'EMPTY - negative' => [false, '2020-02-07 00:00:00', '2020-02-06 02:00:00', Rule::OPERATOR_LTE],
            'EMPTY - negative 2' => [false, '2020-02-07 00:00:00', null, Rule::OPERATOR_EMPTY],
            'EMPTY - positive' => [true, null, '2020-02-06 02:00:00', Rule::OPERATOR_EMPTY],
        ];
    }

    /**
     * This test verifies that our rule works correctly
     * with all the different operators and values.
     *
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(bool $expected, ?string $itemReleased, ?string $ruleDate, string $operator): void
    {
        $this->rule->assign(['lineItemReleaseDate' => $ruleDate, 'operator' => $operator]);

        $isMatching = $this->rule->match(new LineItemScope(
            $this->createLineItemWithReleaseDate($itemReleased),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $isMatching);
    }

    public function testItemWithoutReleaseDateIsFalse(): void
    {
        $scope = new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        );

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidDateValueIsFalse(): void
    {
        $this->rule->assign([
            'lineItemReleaseDate' => 'invalid-date-value',
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testInvalidScope(): void
    {
        $this->rule->assign([
            'lineItemReleaseDate' => '2020-02-06 00:00:00',
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $match = $this->rule->match(new CheckoutRuleScope(
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScope(
        string $ruleReleaseDate,
        string $lineItemReleaseDate1,
        string $lineItemReleaseDate2,
        bool $expected
    ): void {
        $this->rule->assign([
            'lineItemReleaseDate' => $ruleReleaseDate,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithReleaseDate($lineItemReleaseDate1),
            $this->createLineItemWithReleaseDate($lineItemReleaseDate2),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScopeNested(
        string $ruleReleaseDate,
        string $lineItemReleaseDate1,
        string $lineItemReleaseDate2,
        bool $expected
    ): void {
        $this->rule->assign([
            'lineItemReleaseDate' => $ruleReleaseDate,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithReleaseDate($lineItemReleaseDate1),
            $this->createLineItemWithReleaseDate($lineItemReleaseDate2),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'no match' => ['2020-02-06 00:00:00', '2020-01-01 12:30:00', '2020-01-01 18:00:00', false],
            'one matching' => ['2020-02-06 00:00:00', '2020-02-06 00:00:00', '2020-01-01 18:00:00', true],
            'all matching' => ['2020-02-06 00:00:00', '2020-02-06 00:00:00', '2020-02-06 00:00:00', true],
        ];
    }

    private function createLineItemWithReleaseDate(?string $releaseDate): LineItem
    {
        if ($releaseDate === null) {
            $this->createLineItem();
        }

        return ($this->createLineItem())->setPayloadValue(self::PAYLOAD_KEY, $releaseDate);
    }
}
