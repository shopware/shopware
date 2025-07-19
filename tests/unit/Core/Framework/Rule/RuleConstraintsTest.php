<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleConstraints::class)]
class RuleConstraintsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $defaultOperators = [
        Rule::OPERATOR_NEQ,
        Rule::OPERATOR_GTE,
        Rule::OPERATOR_LTE,
        Rule::OPERATOR_EQ,
        Rule::OPERATOR_GT,
        Rule::OPERATOR_LT,
    ];

    public function testDateConstraints(): void
    {
        $constraints = RuleConstraints::date();

        static::assertCount(2, $constraints);
        static::assertInstanceOf(NotBlank::class, $constraints[0]);
        static::assertInstanceOf(Type::class, $constraints[1]);
    }

    public function testDateTimeConstraints(): void
    {
        $constraints = RuleConstraints::datetime();

        static::assertCount(2, $constraints);
        static::assertInstanceOf(NotBlank::class, $constraints[0]);
        static::assertInstanceOf(Type::class, $constraints[1]);
    }

    public function testDateOperators(): void
    {
        $operators = RuleConstraints::dateOperators(false);

        static::assertCount(2, $operators);
        static::assertInstanceOf(NotBlank::class, $operators[0]);
        static::assertInstanceOf(Choice::class, $operators[1]);
        static::assertEquals(new Choice($this->defaultOperators), $operators[1]);

        $operators = RuleConstraints::dateOperators();

        static::assertCount(2, $operators);
        static::assertInstanceOf(NotBlank::class, $operators[0]);
        static::assertInstanceOf(Choice::class, $operators[1]);
        static::assertEquals(
            new Choice([...$this->defaultOperators, Rule::OPERATOR_EMPTY]),
            $operators[1],
        );
    }

    public function testDateTimeOperators(): void
    {
        $operators = RuleConstraints::datetimeOperators(false);

        static::assertCount(2, $operators);
        static::assertInstanceOf(NotBlank::class, $operators[0]);
        static::assertInstanceOf(Choice::class, $operators[1]);
        static::assertEquals(new Choice($this->defaultOperators), $operators[1]);

        $operators = RuleConstraints::datetimeOperators();

        static::assertCount(2, $operators);
        static::assertInstanceOf(NotBlank::class, $operators[0]);
        static::assertInstanceOf(Choice::class, $operators[1]);
        static::assertEquals(
            new Choice([...$this->defaultOperators, Rule::OPERATOR_EMPTY]),
            $operators[1],
        );
    }
}
