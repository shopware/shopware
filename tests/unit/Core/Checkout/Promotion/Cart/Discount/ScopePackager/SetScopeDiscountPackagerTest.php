<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(SetScopeDiscountPackager::class)]
#[Package('checkout')]
class SetScopeDiscountPackagerTest extends TestCase
{
    public function testResolvesRulesFromPersistedPayload(): void
    {
        $rule = new RuleEntity();
        $rule->setId(Uuid::randomHex());
        $rule->setPayload(new AlwaysValidRule());
        $rules = new RuleCollection([$rule]);

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn($rules);

        $groupBuilder = static::createStub(LineItemGroupBuilder::class);
        $groupBuilder->method('findGroupPackages')->willReturnCallback(static function (array $definitions) use ($rule): LineItemGroupBuilderResult {
            static::assertCount(1, $definitions);
            static::assertInstanceOf(LineItemGroupDefinition::class, $definitions[0]);
            static::assertSame($rule, $definitions[0]->getRules()->first());
            static::assertInstanceOf(AlwaysValidRule::class, $definitions[0]->getRules()->first()->getPayload());

            return new LineItemGroupBuilderResult();
        });

        $serializedRules = json_decode(json_encode($rules, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        (new SetScopeDiscountPackager($groupBuilder, $ruleLoader))->getMatchingItems(
            $this->createDiscount([['rules' => $serializedRules]]),
            new Cart('token'),
            Generator::generateSalesChannelContext(),
        );
    }

    public function testKeepsRulesFromLiveCart(): void
    {
        $rules = new RuleCollection();

        $ruleLoader = static::createMock(AbstractRuleLoader::class);
        $ruleLoader->expects($this->never())->method('load');

        $groupBuilder = static::createStub(LineItemGroupBuilder::class);
        $groupBuilder->method('findGroupPackages')->willReturnCallback(static function (array $definitions) use ($rules): LineItemGroupBuilderResult {
            static::assertInstanceOf(LineItemGroupDefinition::class, $definitions[0]);
            static::assertSame($rules, $definitions[0]->getRules());

            return new LineItemGroupBuilderResult();
        });

        (new SetScopeDiscountPackager($groupBuilder, $ruleLoader))->getMatchingItems(
            $this->createDiscount([['rules' => $rules]]),
            new Cart('token'),
            Generator::generateSalesChannelContext(),
        );
    }

    public function testFailsClosedWhenPersistedRuleCannotBeLoaded(): void
    {
        $groupId = Uuid::randomHex();

        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn(new RuleCollection());

        static::expectExceptionObject(PromotionException::promotionSetGroupNotFound($groupId));

        (new SetScopeDiscountPackager(static::createStub(LineItemGroupBuilder::class), $ruleLoader))->getMatchingItems(
            $this->createDiscount([[
                'groupId' => $groupId,
                'rules' => [['id' => Uuid::randomHex()]],
            ]]),
            new Cart('token'),
            Generator::generateSalesChannelContext(),
        );
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function createDiscount(array $groups): DiscountLineItem
    {
        $groups = array_map(static fn (array $group): array => $group + [
            'groupId' => Uuid::randomHex(),
            'packagerKey' => 'COUNT',
            'value' => 1,
            'sorterKey' => 'PRICE_ASC',
        ], $groups);

        return new DiscountLineItem(
            'discount',
            new AbsolutePriceDefinition(10),
            [
                'discountScope' => 'set',
                'discountType' => 'absolute',
                'setGroups' => $groups,
            ],
            null,
        );
    }
}
