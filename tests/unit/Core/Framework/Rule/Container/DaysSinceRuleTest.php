<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Tests\Unit\Core\Framework\Rule\Fixture\DaysSinceRuleFixture;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(DaysSinceRule::class)]
class DaysSinceRuleTest extends TestCase
{
    private DaysSinceRuleFixture $rule;

    protected function setUp(): void
    {
        $this->rule = new DaysSinceRuleFixture();
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('daysPassed', $constraints, 'daysPassed constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::float(), $constraints['daysPassed']);
        static::assertEquals(RuleConstraints::numericOperators(), $constraints['operator']);
    }

    public function testRuleConfig(): void
    {
        $config = $this->rule->getConfig()->getData();

        static::assertArrayHasKey('operatorSet', $config);
        static::assertArrayHasKey('fields', $config);

        $operators = RuleConfig::OPERATOR_SET_NUMBER;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $config['operatorSet']);

        static::assertEquals([
            'name' => 'daysPassed',
            'type' => 'float',
            'config' => [
                'unit' => 'time',
                'digits' => RuleConfig::DEFAULT_DIGITS,
            ],
        ], $config['fields']['daysPassed']);
    }
}
