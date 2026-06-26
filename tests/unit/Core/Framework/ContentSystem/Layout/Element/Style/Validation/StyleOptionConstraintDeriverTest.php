<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(StyleOptionConstraintDeriver::class)]
class StyleOptionConstraintDeriverTest extends TestCase
{
    private StyleOptionConstraintDeriver $deriver;

    protected function setUp(): void
    {
        $this->deriver = new StyleOptionConstraintDeriver();
    }

    #[TestDox('a boolean derives a bool type constraint only, never NotBlank (false is valid)')]
    public function testBooleanDerivesTypeOnly(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('boolean', null, null, null, null));

        static::assertCount(1, $constraints);
        static::assertSame('bool', $this->only(Type::class, $constraints)->type);
    }

    #[TestDox('an integer with a range derives int Type, NotBlank and a Range matching the bounds')]
    public function testIntegerRangeDerivesTypeNotBlankAndRange(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null));

        static::assertSame('int', $this->only(Type::class, $constraints)->type);
        static::assertTrue($this->has(NotBlank::class, $constraints));

        $range = $this->only(Range::class, $constraints);
        static::assertSame(1, $range->min);
        static::assertSame(12, $range->max);
    }

    #[TestDox('a number derives a numeric Type and NotBlank')]
    public function testNumberDerivesNumericType(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('number', null, null, null, null));

        static::assertSame('numeric', $this->only(Type::class, $constraints)->type);
        static::assertTrue($this->has(NotBlank::class, $constraints));
    }

    #[TestDox('a number caps its serialized length at the default so a numeric string cannot be unbounded')]
    public function testNumberDerivesDefaultLengthCap(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('number', null, null, null, null));

        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $this->only(Length::class, $constraints)->max);
    }

    #[TestDox('a number with a range derives numeric Type, NotBlank and a Range matching the float bounds')]
    public function testNumberRangeDerivesRange(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('number', null, ['min' => 0.5, 'max' => 1.5], null, null));

        static::assertSame('numeric', $this->only(Type::class, $constraints)->type);
        static::assertTrue($this->has(NotBlank::class, $constraints));

        $range = $this->only(Range::class, $constraints);
        static::assertSame(0.5, $range->min);
        static::assertSame(1.5, $range->max);
    }

    #[TestDox('a string derives string Type, NotBlank and a Length bounded by the declared maxLength')]
    public function testStringDerivesTypeNotBlankAndLength(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('string', null, null, 64, null));

        static::assertSame('string', $this->only(Type::class, $constraints)->type);
        static::assertTrue($this->has(NotBlank::class, $constraints));
        static::assertSame(64, $this->only(Length::class, $constraints)->max);
    }

    #[TestDox('a string enum derives a strict Choice and a Length at the default cap')]
    public function testStringEnumDerivesStrictChoiceAndDefaultLength(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('string', ['start', 'center', 'end'], null, null, null));

        $choice = $this->only(Choice::class, $constraints);
        static::assertTrue($choice->strict);
        static::assertSame(['start', 'center', 'end'], $choice->choices);
        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $this->only(Length::class, $constraints)->max);
    }

    #[TestDox('an integer enum derives a strict Choice over the declared values')]
    public function testIntegerEnumDerivesStrictChoice(): void
    {
        $constraints = $this->deriver->derive(new StyleOptionValueType('integer', [1, 2, 3], null, null, null));

        $choice = $this->only(Choice::class, $constraints);
        static::assertTrue($choice->strict);
        static::assertSame([1, 2, 3], $choice->choices);
    }

    #[TestDox('an unsupported value type fails hard')]
    public function testUnsupportedTypeThrows(): void
    {
        $this->expectExceptionObject(ContentSystemException::unsupportedStyleValueType('object'));

        $this->deriver->derive(new StyleOptionValueType('object', null, null, null, null));
    }

    /**
     * @template TConstraint of Constraint
     *
     * @param class-string<TConstraint> $class
     * @param list<Constraint> $constraints
     *
     * @return TConstraint
     */
    private function only(string $class, array $constraints): Constraint
    {
        $matches = array_values(array_filter($constraints, static fn (Constraint $c): bool => $c instanceof $class));

        static::assertCount(1, $matches, \sprintf('expected exactly one %s', $class));

        return $matches[0];
    }

    /**
     * @param class-string<Constraint> $class
     * @param list<Constraint> $constraints
     */
    private function has(string $class, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
