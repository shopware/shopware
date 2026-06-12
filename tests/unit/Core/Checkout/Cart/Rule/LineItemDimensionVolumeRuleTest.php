<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemDimensionVolumeRule::class)]
#[Group('rules')]
class LineItemDimensionVolumeRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionVolumeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionVolumeRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionVolume', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        ?float $volume,
        float $lineItemVolume,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithVolume($lineItemVolume * Rule::VOLUME_FACTOR);
        if ($lineItemWithoutDeliveryInfo) {
            $lineItem = $this->createLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same volume' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different volume' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 100, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same volume' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different volume' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower volume' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same volume' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher volume' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower volume' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same volume' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher volume' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower volume' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same volume' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher volume' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower volume' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same volume' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher volume' => [Rule::OPERATOR_LTE, 100, 200, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, null, 200, true, true];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $volume,
        float $lineItemVolume1,
        float $lineItemVolume2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemVolume = null
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithVolume($lineItemVolume1 * Rule::VOLUME_FACTOR);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = self::createLineItem();
        }

        $lineItem2 = $this->createLineItemWithVolume($lineItemVolume2 * Rule::VOLUME_FACTOR);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = self::createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        ?float $volume,
        float $lineItemVolume1,
        float $lineItemVolume2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemVolume = null
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithVolume($lineItemVolume1 * Rule::VOLUME_FACTOR);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithVolume($lineItemVolume2 * Rule::VOLUME_FACTOR);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = $this->createLineItem();
        if ($containerLineItemVolume !== null) {
            $containerLineItem = $this->createLineItemWithVolume($containerLineItemVolume * Rule::VOLUME_FACTOR);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same volume' => [
            'operator' => Rule::OPERATOR_EQ,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 200,
            'expected' => true,
        ];
        yield 'no match / operator equals / different volume' => [
            'operator' => Rule::OPERATOR_EQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => false,
        ];
        yield 'no match / operator equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same volume' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 100,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => false,
            'containerLineItemVolume' => 100,
        ];
        yield 'match / operator not equals / different volume' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 200,
            'expected' => true,
        ];
        yield 'match / operator not equals / different volume 2' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => true,
        ];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower volume' => [
            'operator' => Rule::OPERATOR_GT,
            'volume' => 100,
            'lineItemVolume1' => 50,
            'lineItemVolume2' => 70,
            'expected' => false,
        ];
        yield 'no match / operator greater than / same volume' => [
            'operator' => Rule::OPERATOR_GT,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 70,
            'expected' => false,
        ];
        yield 'match / operator greater than / higher volume' => [
            'operator' => Rule::OPERATOR_GT,
            'volume' => 100,
            'lineItemVolume1' => 200,
            'lineItemVolume2' => 70,
            'expected' => true,
        ];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower volume' => [
            'operator' => Rule::OPERATOR_GTE,
            'volume' => 100,
            'lineItemVolume1' => 50,
            'lineItemVolume2' => 70,
            'expected' => false,
        ];
        yield 'match / operator greater than equals / same volume' => [
            'operator' => Rule::OPERATOR_GTE,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 70,
            'expected' => true,
        ];
        yield 'match / operator greater than equals / higher volume' => [
            'operator' => Rule::OPERATOR_GTE,
            'volume' => 100,
            'lineItemVolume1' => 200,
            'lineItemVolume2' => 70,
            'expected' => true,
        ];
        // OPERATOR_LT
        yield 'match / operator lower than / lower volume' => [
            'operator' => Rule::OPERATOR_LT,
            'volume' => 100,
            'lineItemVolume1' => 50,
            'lineItemVolume2' => 120,
            'expected' => true,
        ];
        yield 'no match / operator lower  than / same volume' => [
            'operator' => Rule::OPERATOR_LT,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 120,
            'expected' => false,
        ];
        yield 'no match / operator lower than / higher volume' => [
            'operator' => Rule::OPERATOR_LT,
            'volume' => 100,
            'lineItemVolume1' => 200,
            'lineItemVolume2' => 120,
            'expected' => false,
        ];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower volume' => [
            'operator' => Rule::OPERATOR_LTE,
            'volume' => 100,
            'lineItemVolume1' => 50,
            'lineItemVolume2' => 120,
            'expected' => true,
        ];
        yield 'match / operator lower than equals / same volume' => [
            'operator' => Rule::OPERATOR_LTE,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 120,
            'expected' => true,
        ];
        yield 'no match / operator lower than equals / higher volume' => [
            'operator' => Rule::OPERATOR_LTE,
            'volume' => 100,
            'lineItemVolume1' => 200,
            'lineItemVolume2' => 120,
            'expected' => false,
        ];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 200,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'volume' => 100,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'volume' => null,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 300,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'volume' => null,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'volume' => null,
            'lineItemVolume1' => 100,
            'lineItemVolume2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
    }

    public function testMatchWithUnsupportedScopeShouldReturnFalse(): void
    {
        $scope = new TestRuleScope($this->createMock(SalesChannelContext::class));

        $lineItemDimensionVolumeRule = new LineItemDimensionVolumeRule();

        static::assertFalse($lineItemDimensionVolumeRule->match($scope));
    }

    public function testGetConfig(): void
    {
        $lineItemDimensionVolumeRule = new LineItemDimensionVolumeRule();

        $result = $lineItemDimensionVolumeRule->getConfig();

        static::assertSame(RuleConfig::OPERATOR_SET_NUMBER, $result->getData()['operatorSet']['operators']);
        static::assertSame(RuleConfig::UNIT_VOLUME, $result->getData()['fields']['amount']['config']['unit']);
    }

    private function createLineItemWithVolume(float $volume): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 50, $volume, 1, 1);
    }
}
