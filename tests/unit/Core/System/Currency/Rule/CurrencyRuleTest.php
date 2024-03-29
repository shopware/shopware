<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CurrencyRule::class)]
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
            'operator' => RuleConstraints::uuidOperators(false),
            'currencyIds' => RuleConstraints::uuids(),
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
                'currencyIds' => [
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
     * @param list<string> $currencyIds
     */
    #[DataProvider('matchCurrencyRuleDataProvider')]
    public function testMatch(string $operator, string $currencyId, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertTrue($rule->match($this->getCartRuleScope($currencyId)));
    }

    /**
     * @param list<string> $currencyIds
     */
    #[DataProvider('noMatchCurrencyRuleDataProvider')]
    public function testNoMatch(string $operator, string $currencyId, array $currencyIds): void
    {
        $rule = new CurrencyRule($operator, $currencyIds);

        static::assertFalse($rule->match($this->getCartRuleScope($currencyId)));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function matchCurrencyRuleDataProvider(): iterable
    {
        yield 'It matches one currency, equals operator' => [
            Rule::OPERATOR_EQ,
            'id-1',
            [
                'id-1',
            ],
        ];

        yield 'It matches one currency, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'different-currency-id',
            [
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, equals operator' => [
            Rule::OPERATOR_EQ,
            'id-1',
            [
                'id-2',
                'id-3',
                'id-1',
            ],
        ];

        yield 'It matches multiple currencies, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'different-currency-id',
            [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function noMatchCurrencyRuleDataProvider(): iterable
    {
        $ids = new IdsCollection();

        yield 'It does not matches one currency, equals operator' => [
            Rule::OPERATOR_EQ,
            'different-currency-id',
            [
                'id-1',
            ],
        ];

        yield 'It does not matches one currency, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'id-1',
            [
                'id-1',
            ],
        ];

        yield 'It does not matches multiple currencies, equals operator' => [
            Rule::OPERATOR_EQ,
            $ids->get('different-currency-id'),
            [
                'id-1',
                'id-2',
                'id-3',
            ],
        ];

        yield 'It does not matches multiple currencies, not equals operator' => [
            Rule::OPERATOR_NEQ,
            'id-1',
            [
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

        $cart = new Cart('bar');

        return new CartRuleScope($cart, $salesChannelContext);
    }
}
