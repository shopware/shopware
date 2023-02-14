<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('business-ops')]
class MatchAllLineItemsRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use CartRuleHelperTrait;

    /**
     * @var EntityRepository
     */
    private $ruleRepository;

    /**
     * @var EntityRepository
     */
    private $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithInvalidRulesType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new MatchAllLineItemsRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'rules' => ['Rule'],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/rules', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testIfRuleIsConsistent(): void
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
                'type' => (new MatchAllLineItemsRule())->getName(),
                'ruleId' => $ruleId,
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testIfRuleWithChildRulesIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $categoryIds = [Uuid::randomHex()];
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new MatchAllLineItemsRule())->getName(),
                'ruleId' => $ruleId,
                'children' => [
                    [
                        'type' => (new LineItemInCategoryRule())->getName(),
                        'ruleId' => $ruleId,
                        'value' => [
                            'operator' => MatchAllLineItemsRule::OPERATOR_EQ,
                            'categoryIds' => $categoryIds,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
        /** @var RuleEntity $ruleStruct */
        $ruleStruct = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);
        static::assertEquals(new AndRule([new MatchAllLineItemsRule([new LineItemInCategoryRule(MatchAllLineItemsRule::OPERATOR_EQ, $categoryIds)])]), $ruleStruct->getPayload());
    }

    /**
     * @dataProvider getCartScopeTestData
     *
     * @param array<string> $categoryIds
     */
    public function testIfMatchesAllCorrectWithLineItemScope(
        array $categoryIdsProductA,
        array $categoryIdsProductB,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule();
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsProductA),
            $this->createLineItemWithCategories($categoryIdsProductB),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getCartScopeTestData(): array
    {
        return [
            'all products / equal / match category id' => [['1', '2'], ['1', '3'], MatchAllLineItemsRule::OPERATOR_EQ, ['1'], true],
            'all products / equal / no match category id' => [['1', '2'], ['2', '3'], MatchAllLineItemsRule::OPERATOR_EQ, ['1'], false],
            'all products / not equal / match category id' => [['2', '3'], ['2', '3'], MatchAllLineItemsRule::OPERATOR_NEQ, ['1'], true],
            'all products / not equal / no match category id' => [['2', '3'], ['1', '2'], MatchAllLineItemsRule::OPERATOR_NEQ, ['1'], false],
            'all products / empty / match category id' => [[], [], MatchAllLineItemsRule::OPERATOR_EMPTY, [], true],
            'all products / empty / no match category id' => [[], ['1', '2'], MatchAllLineItemsRule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @dataProvider getCartScopeTestMinimumShouldMatchData
     *
     * @param array<string> $categoryIds
     */
    public function testIfMatchesMinimumCorrectWithLineItemScope(
        array $categoryIdsProductA,
        array $categoryIdsProductB,
        array $categoryIdsProductC,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule([], null, 'product');
        $allLineItemsRule->assign(['minimumShouldMatch' => 2]);
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsProductA),
            $this->createLineItemWithCategories($categoryIdsProductB),
            $this->createLineItemWithCategories($categoryIdsProductC),
        ]);

        $promotionLineItem = ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A');
        $lineItemCollection->add($promotionLineItem);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getCartScopeTestMinimumShouldMatchData(): array
    {
        return [
            'minimum 2 products / equal / match category id' => [['1', '2'], ['1', '3'], ['2', '3'], MatchAllLineItemsRule::OPERATOR_EQ, ['1'], true],
            'minimum 2 products / equal / no match category id' => [['1', '2'], ['2', '3'], ['2', '3'], MatchAllLineItemsRule::OPERATOR_EQ, ['1'], false],
            'minimum 2 products / not equal / match category id' => [['2', '3'], ['2', '3'], ['1', '3'], MatchAllLineItemsRule::OPERATOR_NEQ, ['1'], true],
            'minimum 2 products / not equal / no match category id' => [['2', '3'], ['1', '2'], ['1', '2'], MatchAllLineItemsRule::OPERATOR_NEQ, ['1'], false],
            'minimum 2 products / empty / match category id' => [[], [], [], MatchAllLineItemsRule::OPERATOR_EMPTY, [], true],
            'minimum 2 products / empty / no match category id' => [[], ['1', '2'], ['2', '3'], MatchAllLineItemsRule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @param array<string> $categoryIds
     */
    private function createLineItemWithCategories(array $categoryIds): LineItem
    {
        return $this->createLineItem()->setPayloadValue('categoryIds', $categoryIds);
    }
}
