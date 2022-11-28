<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package business-ops
 *
 * @internal
 * @covers \Shopware\Core\System\Currency\Rule\CurrencyRule
 */
class CurrencyRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new CurrencyRule();

        static::assertEquals('currency', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new CurrencyRule();

        static::assertEquals([
            'currencyIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ], $rule->getConstraints());
    }

    public function testGetConfig(): void
    {
        $rule = new CurrencyRule();
        $ruleConfig = $rule->getConfig();

        static::assertEquals([
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                ],
                'isMatchAny' => true,
            ],
            'fields' => [
                [
                    'name' => 'currencyIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'currency',
                    ],
                ],
            ],
        ], $ruleConfig->getData());
    }

    /**
     * @dataProvider matchCurrencyRuleDataProvider
     *
     * @param list<string> $currencyIds
     */
    public function testMatch(string $operator, CartRuleScope $cartRuleScope, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertTrue($rule->match($cartRuleScope));
    }

    /**
     * @dataProvider noMatchCurrencyRuleDataProvider
     *
     * @param list<string> $currencyIds
     */
    public function testNoMatch(string $operator, CartRuleScope $cartRuleScope, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertFalse($rule->match($cartRuleScope));
    }

    /**
     * @return array<string, array{operator: string, cartRuleScope: CartRuleScope, currencyIds: list<string>}>
     */
    public function matchCurrencyRuleDataProvider(): iterable
    {
        yield 'It matches one currency, equals operator' => [
            'operator' => Rule::OPERATOR_EQ,
            'cartRuleScope' => $this->getCartRuleScope('id-1'),
            'currencyIds' => [
                'id-1',
            ],
        ];

        yield 'It matches one currency, not equals operator' => [
            'operator' => Rule::OPERATOR_NEQ,
            'cartRuleScope' => $this->getCartRuleScope('different-currency-id'),
            'currencyIds' => [
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, equals operator' => [
            'operator' => Rule::OPERATOR_EQ,
            'cartRuleScope' => $this->getCartRuleScope('id-1'),
            'currencyIds' => [
                'id-2',
                'id-3',
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, not equals operator' => [
            'operator' => Rule::OPERATOR_NEQ,
            'cartRuleScope' => $this->getCartRuleScope('different-currency-id'),
            'currencyIds' => [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];
    }

    /**
     * @return array<string, array{operator: string, cartRuleScope: CartRuleScope, currencyIds: list<string>}>
     */
    public function noMatchCurrencyRuleDataProvider(): iterable
    {
        $ids = new IdsCollection();

        yield 'It does not matches one currency, equals operator' => [
            'operator' => Rule::OPERATOR_EQ,
            'cartRuleScope' => $this->getCartRuleScope('different-currency-id'),
            'currencyIds' => [
                'id-1',
            ],
        ];

        yield 'It does not matches one currency, not equals operator' => [
            'operator' => Rule::OPERATOR_NEQ,
            'cartRuleScope' => $this->getCartRuleScope('id-1'),
            'currencyIds' => [
                'id-1',
            ],
        ];

        yield 'It does not matches multiple currencies, equals operator' => [
            'operator' => Rule::OPERATOR_EQ,
            'cartRuleScope' => $this->getCartRuleScope($ids->get('different-currency-id')),
            'currencyIds' => [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];

        yield 'It does not matches multiple currencies, not equals operator' => [
            'operator' => Rule::OPERATOR_NEQ,
            'cartRuleScope' => $this->getCartRuleScope('id-1'),
            'currencyIds' => [
                'id-2',
                'id-3',
                'id-1',
            ],
        ];
    }

    private function getCartRuleScope(string $currencyId): CartRuleScope
    {
        $context = Context::createDefaultContext();
        $context->assign(['currencyId' => $currencyId]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        $cart = new Cart('foo', 'bar');

        return new CartRuleScope($cart, $salesChannelContext);
    }
}
