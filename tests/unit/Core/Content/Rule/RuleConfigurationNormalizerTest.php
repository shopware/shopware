<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerLoggedInRule;
use Shopware\Core\Content\Rule\RuleConfigurationNormalizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;

/**
 * @internal
 *
 * @phpstan-import-type ConditionRow from RuleConfigurationNormalizer
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleConfigurationNormalizer::class)]
class RuleConfigurationNormalizerTest extends TestCase
{
    private RuleConfigurationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new RuleConfigurationNormalizer(new RuleConditionRegistry([
            new AndRule(),
            new OrRule(),
            new CartAmountRule(),
            new CustomerLoggedInRule(),
        ]));
    }

    public function testNormalizeBuildsTreeWithSortedKeysAndWithoutIdentity(): void
    {
        $tree = $this->normalizer->normalize([
            self::condition(id: 'root', type: 'orContainer'),
            self::condition(id: 'child', type: 'customerGroup', value: '{"operator":"=","customerGroupIds":["b","a"]}', parentId: 'root'),
        ]);

        static::assertSame([
            [
                'children' => [
                    [
                        'children' => [],
                        'scriptId' => null,
                        'type' => 'customerGroup',
                        'value' => ['customerGroupIds' => ['a', 'b'], 'operator' => '='],
                    ],
                ],
                'scriptId' => null,
                'type' => 'orContainer',
                'value' => [],
            ],
        ], $tree);
    }

    /**
     * @param list<ConditionRow> $first
     * @param list<ConditionRow> $second
     */
    #[DataProvider('equivalentConfigurationProvider')]
    public function testEquivalentConfigurationsShareChecksum(array $first, array $second): void
    {
        static::assertSame($this->normalizer->checksum($first), $this->normalizer->checksum($second));
    }

    public static function equivalentConfigurationProvider(): \Generator
    {
        yield 'JSON key order of the value does not matter' => [
            [self::condition(id: 'a', type: 'cartCartAmount', value: '{"operator":">=","amount":100}')],
            [self::condition(id: 'b', type: 'cartCartAmount', value: '{"amount":100,"operator":">="}')],
        ];

        yield 'nested JSON key order of the value does not matter' => [
            [self::condition(id: 'a', type: 'customerCustomField', value: '{"renderedField":{"name":"x","type":"text"},"operator":"="}')],
            [self::condition(id: 'b', type: 'customerCustomField', value: '{"operator":"=","renderedField":{"type":"text","name":"x"}}')],
        ];

        yield 'order of sibling conditions does not matter' => [
            [
                self::condition(id: 'a1', type: 'andContainer'),
                self::condition(id: 'a2', type: 'cartCartAmount', value: '{"operator":">=","amount":100}', parentId: 'a1'),
                self::condition(id: 'a3', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'a1'),
            ],
            [
                self::condition(id: 'b1', type: 'andContainer'),
                self::condition(id: 'b3', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'b1'),
                self::condition(id: 'b2', type: 'cartCartAmount', value: '{"operator":">=","amount":100}', parentId: 'b1'),
            ],
        ];

        yield 'order of list values does not matter' => [
            [self::condition(id: 'a', type: 'customerGroup', value: '{"operator":"=","customerGroupIds":["x","y","z"]}')],
            [self::condition(id: 'b', type: 'customerGroup', value: '{"operator":"=","customerGroupIds":["z","x","y"]}')],
        ];

        yield 'missing value equals an empty JSON object' => [
            [self::condition(id: 'a', type: 'customerLoggedIn')],
            [self::condition(id: 'b', type: 'customerLoggedIn', value: '{}')],
        ];

        yield 'missing value equals an empty JSON list' => [
            [self::condition(id: 'a', type: 'customerLoggedIn')],
            [self::condition(id: 'b', type: 'customerLoggedIn', value: '[]')],
        ];

        yield 'missing value equals a JSON null literal' => [
            [self::condition(id: 'a', type: 'customerLoggedIn')],
            [self::condition(id: 'b', type: 'customerLoggedIn', value: 'null')],
        ];
    }

    /**
     * @param list<ConditionRow> $first
     * @param list<ConditionRow> $second
     */
    #[DataProvider('differentConfigurationProvider')]
    public function testDifferentConfigurationsHaveDifferentChecksums(array $first, array $second): void
    {
        static::assertNotSame($this->normalizer->checksum($first), $this->normalizer->checksum($second));
    }

    public static function differentConfigurationProvider(): \Generator
    {
        yield 'a different operator changes the checksum' => [
            [self::condition(id: 'a', type: 'cartCartAmount', value: '{"operator":">=","amount":100}')],
            [self::condition(id: 'b', type: 'cartCartAmount', value: '{"operator":"<=","amount":100}')],
        ];

        yield 'a different value changes the checksum' => [
            [self::condition(id: 'a', type: 'cartCartAmount', value: '{"operator":">=","amount":100}')],
            [self::condition(id: 'b', type: 'cartCartAmount', value: '{"operator":">=","amount":200}')],
        ];

        yield 'a different value type changes the checksum' => [
            [self::condition(id: 'a', type: 'cartCartAmount', value: '{"operator":">=","amount":100}')],
            [self::condition(id: 'b', type: 'cartCartAmount', value: '{"operator":">=","amount":"100"}')],
        ];

        yield 'a scalar JSON value is kept instead of being treated as empty' => [
            [self::condition(id: 'a', type: 'customerLoggedIn')],
            [self::condition(id: 'b', type: 'customerLoggedIn', value: '"legacy"')],
        ];

        yield 'a different container type changes the checksum' => [
            [
                self::condition(id: 'a1', type: 'andContainer'),
                self::condition(id: 'a2', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'a1'),
            ],
            [
                self::condition(id: 'b1', type: 'orContainer'),
                self::condition(id: 'b2', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'b1'),
            ],
        ];

        yield 'a different app script changes the checksum' => [
            [self::condition(id: 'a', type: 'scriptRule', scriptId: 'script-1')],
            [self::condition(id: 'b', type: 'scriptRule', scriptId: 'script-2')],
        ];

        yield 'a different nesting changes the checksum' => [
            [
                self::condition(id: 'a1', type: 'andContainer'),
                self::condition(id: 'a2', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'a1'),
                self::condition(id: 'a3', type: 'orContainer', parentId: 'a1'),
                self::condition(id: 'a4', type: 'cartCartAmount', value: '{"operator":">=","amount":100}', parentId: 'a3'),
            ],
            [
                self::condition(id: 'b1', type: 'andContainer'),
                self::condition(id: 'b2', type: 'customerLoggedIn', value: '{"isLoggedIn":true}', parentId: 'b1'),
                self::condition(id: 'b3', type: 'orContainer', parentId: 'b1'),
                self::condition(id: 'b4', type: 'cartCartAmount', value: '{"operator":">=","amount":100}', parentId: 'b1'),
            ],
        ];

        yield 'an additional condition changes the checksum' => [
            [self::condition(id: 'a', type: 'customerLoggedIn', value: '{"isLoggedIn":true}')],
            [
                self::condition(id: 'b1', type: 'customerLoggedIn', value: '{"isLoggedIn":true}'),
                self::condition(id: 'b2', type: 'customerLoggedIn', value: '{"isLoggedIn":true}'),
            ],
        ];
    }

    /**
     * @param list<ConditionRow> $conditions
     */
    #[DataProvider('withoutActualConditionProvider')]
    public function testRulesWithoutActualConditionHaveNoChecksum(array $conditions): void
    {
        static::assertNull($this->normalizer->checksum($conditions));
    }

    public static function withoutActualConditionProvider(): \Generator
    {
        yield 'a rule without conditions has no checksum' => [[]];

        yield 'a rule with only empty containers has no checksum' => [[
            self::condition(id: 'a1', type: 'orContainer'),
            self::condition(id: 'a2', type: 'andContainer', parentId: 'a1'),
        ]];
    }

    public function testUnknownConditionTypeCountsAsActualCondition(): void
    {
        static::assertNotNull($this->normalizer->checksum([
            self::condition(id: 'a1', type: 'andContainer'),
            self::condition(id: 'a2', type: 'conditionOfUninstalledPlugin', parentId: 'a1'),
        ]));
    }

    /**
     * @return ConditionRow
     */
    private static function condition(
        string $id,
        string $type,
        ?string $value = null,
        ?string $parentId = null,
        ?string $scriptId = null,
    ): array {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'type' => $type,
            'value' => $value,
            'script_id' => $scriptId,
        ];
    }
}
