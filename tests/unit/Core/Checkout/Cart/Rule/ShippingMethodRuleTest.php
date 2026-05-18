<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingMethodRule::class)]
#[Group('rules')]
class ShippingMethodRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new ShippingMethodRule();

        static::assertEquals([
            'shippingMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ], $rule->getConstraints());
    }

    public function testConstraintsRejectEmptyShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectStringShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', '0915d54fbf80423c917c61ad5a391b48');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectInvalidShippingMethodIdsUuid(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', [true, 3, null, '0915d54fbf80423c917c61ad5a391b48']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 3);
    }

    #[DataProvider('validUuidOperators')]
    public function testConstraintsAcceptAvailableOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidUuidOperators')]
    public function testConstraintsRejectInvalidOperators(mixed $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        $this->assertViolationCode($violations, Choice::NO_SUCH_CHOICE_ERROR);
    }

    public function testConstraintsAcceptValidShippingMethodIds(): void
    {
        $violations = $this->validateConstraint('shippingMethodIds', [Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function validUuidOperators(): \Generator
    {
        yield 'equals' => [Rule::OPERATOR_EQ];
        yield 'not equals' => [Rule::OPERATOR_NEQ];
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function invalidUuidOperators(): \Generator
    {
        yield 'less than or equals' => [Rule::OPERATOR_LTE];
        yield 'greater than or equals' => [Rule::OPERATOR_GTE];
        yield 'unknown operator' => ['Invalid'];
        yield 'boolean operator' => [true];
        yield 'float operator' => [1.1];
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new ShippingMethodRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}
