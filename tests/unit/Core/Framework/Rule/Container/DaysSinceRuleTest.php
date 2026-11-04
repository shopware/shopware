<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Framework\Rule\Fixture\DaysSinceRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DaysSinceRule::class)]
class DaysSinceRuleTest extends TestCase
{
    private DaysSinceRuleFixture $rule;

    protected function setUp(): void
    {
        $this->rule = new DaysSinceRuleFixture();
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(RuleException::class);
        }

        $rule = new class extends DaysSinceRule {
            final public const RULE_NAME = 'daysSinceTest';

            protected function getDate(RuleScope $scope): ?\DateTimeInterface
            {
                return null;
            }

            protected function supportsScope(RuleScope $scope): bool
            {
                return true;
            }
        };
        $rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'daysPassed' => null,
        ]);

        $rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class)));
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

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $config['operatorSet']);

        static::assertSame([
            'name' => 'daysPassed',
            'type' => 'float',
            'config' => [
                'unit' => 'time',
                'digits' => RuleConfig::DEFAULT_DIGITS,
            ],
        ], $config['fields']['daysPassed']);
    }
}
