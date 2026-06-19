<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;

/**
 * @internal
 */
#[CoversClass(LabelConfig::class)]
class LabelConfigTest extends TestCase
{
    /**
     * @param array{allowed_values?: list<string>, policy?: string} $definition
     * @param list<string>|null $expectedValues
     */
    #[TestDox('fromDefinition: $_dataName')]
    #[DataProvider('definitionProvider')]
    public function testFromDefinition(array $definition, ?array $expectedValues, ?LabelPolicy $expectedPolicy): void
    {
        $config = LabelConfig::fromDefinition($definition);

        static::assertSame($expectedValues, $config->allowedValues);
        static::assertSame($expectedPolicy, $config->policy);
    }

    /**
     * @return \Generator<string, array{0: array{allowed_values?: list<string>, policy?: string}, 1: list<string>|null, 2: LabelPolicy|null}>
     */
    public static function definitionProvider(): \Generator
    {
        yield 'empty definition yields nulls' => [[], null, null];

        yield 'allowed values only' => [['allowed_values' => ['a', 'b']], ['a', 'b'], null];

        yield 'known policy is parsed to the enum' => [['policy' => 'replace'], null, LabelPolicy::REPLACE];

        yield 'unknown policy resolves to null' => [['policy' => 'nope'], null, null];

        yield 'both values and policy' => [
            ['allowed_values' => ['x'], 'policy' => 'discard'],
            ['x'],
            LabelPolicy::DISCARD,
        ];
    }
}
