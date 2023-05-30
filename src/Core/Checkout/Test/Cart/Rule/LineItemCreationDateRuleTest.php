<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemCreationDateRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const PAYLOAD_KEY = 'createdAt';

    private LineItemCreationDateRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemCreationDateRule();
    }

    public function testName(): void
    {
        static::assertSame('cartLineItemCreationDate', $this->rule->getName());
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
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lineItemCreationDate', $ruleConstraints, 'Constraint lineItemCreationDate not found in Rule');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');

        $date = $ruleConstraints['lineItemCreationDate'];
        $operators = $ruleConstraints['operator'];

        static::assertEquals(new NotBlank(), $date[0]);
        static::assertEquals(new Type(['type' => 'string']), $date[1]);

        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);
    }

    /**
     * @return array<string, array<bool|string>>
     */
    public static function getMatchValues(): array
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
        ];
    }

    /**
     * This test verifies that our rule works correctly
     * with all the different operators and values.
     *
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(bool $expected, string $itemCreated, string $ruleDate, string $operator): void
    {
        $lineItem = $this->createLineItemWithCreatedDate($itemCreated);

        $scope = new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lineItemCreationDate' => $ruleDate, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testItemWithoutCreationDateIsFalse(): void
    {
        $scope = new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        );

        // Rule without date
        static::assertFalse($this->rule->match($scope));

        $this->rule->assign(['lineItemCreationDate' => '2020-02-06 00:00:00']);

        // Rule without line item date with eq operator
        static::assertFalse($this->rule->match($scope));

        $this->rule->assign(['operator' => Rule::OPERATOR_NEQ, 'lineItemCreationDate' => '2020-02-06 00:00:00']);

        // Rule without line item date with neq operator
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidDateValueIsFalse(): void
    {
        $scope = new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lineItemCreationDate' => 'invalid-date-value-text']);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidScope(): void
    {
        $this->rule->assign(['lineItemCreationDate' => '2020-02-06 00:00:00', 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new CheckoutRuleScope(
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScope(
        string $ruleCreationDate,
        string $lineItemCreationDate1,
        string $lineItemCreationDate2,
        bool $expected
    ): void {
        $this->rule->assign(['lineItemCreationDate' => $ruleCreationDate, 'operator' => Rule::OPERATOR_EQ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCreatedDate($lineItemCreationDate1),
            $this->createLineItemWithCreatedDate($lineItemCreationDate2),
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
        string $ruleCreationDate,
        string $lineItemCreationDate1,
        string $lineItemCreationDate2,
        bool $expected
    ): void {
        $this->rule->assign(['lineItemCreationDate' => $ruleCreationDate, 'operator' => Rule::OPERATOR_EQ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCreatedDate($lineItemCreationDate1),
            $this->createLineItemWithCreatedDate($lineItemCreationDate2),
        ]);

        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public static function getCartRuleScopeTestData(): array
    {
        return [
            'no match' => ['2020-02-06 00:00:00', '2020-01-01 12:30:00', '2020-01-01 18:00:00', false],
            'one matching' => ['2020-02-06 00:00:00', '2020-02-06 00:00:00', '2020-01-01 18:00:00', true],
            'all matching' => ['2020-02-06 00:00:00', '2020-02-06 00:00:00', '2020-02-06 00:00:00', true],
        ];
    }

    private function createLineItemWithCreatedDate(string $createdAt): LineItem
    {
        return $this->createLineItem()->setPayloadValue(self::PAYLOAD_KEY, $createdAt);
    }
}
