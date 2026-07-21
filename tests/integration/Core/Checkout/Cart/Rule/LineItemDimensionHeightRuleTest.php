<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[Group('rules')]
class LineItemDimensionHeightRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private LineItemDimensionHeightRule $rule;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->rule = new LineItemDimensionHeightRule();
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionHeight', $this->rule->getName());
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
        float $height,
        ?float $lineItemHeight,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $height,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithHeight($lineItemHeight);
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
        yield 'match / operator equals / same height' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different height' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 100, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same height' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different height' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower height' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same height' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher height' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower height' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same height' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher height' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower height' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same height' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher height' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LT
        yield 'match / operator lower than equals / lower height' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same height' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher height' => [Rule::OPERATOR_LTE, 100, 200, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / null height' => [Rule::OPERATOR_EMPTY, 100, null, true];
        yield 'no match / operator empty / height' => [Rule::OPERATOR_EMPTY, 100, 200, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, 100, 200, true, true];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $height,
        ?float $lineItemHeight1,
        ?float $lineItemHeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemHeight = null
    ): void {
        $this->rule->assign([
            'amount' => $height,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithHeight($lineItemHeight1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithHeight($lineItemHeight2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
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
        float $height,
        ?float $lineItemHeight1,
        ?float $lineItemHeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemHeight = null
    ): void {
        $this->rule->assign([
            'amount' => $height,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithHeight($lineItemHeight1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithHeight($lineItemHeight2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = $this->createLineItem();
        if ($containerLineItemHeight !== null) {
            $containerLineItem = $this->createLineItemWithHeight($containerLineItemHeight);
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
        yield 'match / operator equals / same height' => [
            'operator' => Rule::OPERATOR_EQ,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 200,
            'expected' => true,
        ];
        yield 'no match / operator equals / different height' => [
            'operator' => Rule::OPERATOR_EQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => false,
        ];
        yield 'no match / operator equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same height' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 100,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => false,
            'containerLineItemHeight' => 100,
        ];
        yield 'match / operator not equals / different height' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 200,
            'expected' => true,
        ];
        yield 'match / operator not equals / different height 2' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => true,
        ];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower height' => [
            'operator' => Rule::OPERATOR_GT,
            'height' => 100,
            'lineItemHeight1' => 50,
            'lineItemHeight2' => 70,
            'expected' => false,
        ];
        yield 'no match / operator greater than / same height' => [
            'operator' => Rule::OPERATOR_GT,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 70,
            'expected' => false,
        ];
        yield 'match / operator greater than / higher height' => [
            'operator' => Rule::OPERATOR_GT,
            'height' => 100,
            'lineItemHeight1' => 200,
            'lineItemHeight2' => 70,
            'expected' => true,
        ];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower height' => [
            'operator' => Rule::OPERATOR_GTE,
            'height' => 100,
            'lineItemHeight1' => 50,
            'lineItemHeight2' => 70,
            'expected' => false,
        ];
        yield 'match / operator greater than equals / same height' => [
            'operator' => Rule::OPERATOR_GTE,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 70,
            'expected' => true,
        ];
        yield 'match / operator greater than equals / higher height' => [
            'operator' => Rule::OPERATOR_GTE,
            'height' => 100,
            'lineItemHeight1' => 200,
            'lineItemHeight2' => 70,
            'expected' => true,
        ];
        // OPERATOR_LT
        yield 'match / operator lower than / lower height' => [
            'operator' => Rule::OPERATOR_LT,
            'height' => 100,
            'lineItemHeight1' => 50,
            'lineItemHeight2' => 120,
            'expected' => true,
        ];
        yield 'no match / operator lower  than / same height' => [
            'operator' => Rule::OPERATOR_LT,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 120,
            'expected' => false,
        ];
        yield 'no match / operator lower than / higher height' => [
            'operator' => Rule::OPERATOR_LT,
            'height' => 100,
            'lineItemHeight1' => 200,
            'lineItemHeight2' => 120,
            'expected' => false,
        ];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower height' => [
            'operator' => Rule::OPERATOR_LTE,
            'height' => 100,
            'lineItemHeight1' => 50,
            'lineItemHeight2' => 120,
            'expected' => true,
        ];
        yield 'match / operator lower than equals / same height' => [
            'operator' => Rule::OPERATOR_LTE,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 120,
            'expected' => true,
        ];
        yield 'no match / operator lower than equals / higher height' => [
            'operator' => Rule::OPERATOR_LTE,
            'height' => 100,
            'lineItemHeight1' => 200,
            'lineItemHeight2' => 120,
            'expected' => false,
        ];
        // OPERATOR_EMPTY
        yield 'match / operator empty / null height 1' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 100,
            'lineItemHeight1' => null,
            'lineItemHeight2' => 120,
            'expected' => true,
        ];
        yield 'match / operator empty / null height 2' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => null,
            'expected' => true,
        ];
        yield 'no match / operator empty / height' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 100,
            'lineItemHeight1' => 200,
            'lineItemHeight2' => 120,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => false,
            'containerLineItemHeight' => 200,
        ];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 200,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 300,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'height' => 100,
            'lineItemHeight1' => 100,
            'lineItemHeight2' => 100,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
    }

    public function testValidateWithIntAmount(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemDimensionHeightRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => 3,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    private function createLineItemWithHeight(?float $height): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 50.0, $height);
    }
}
