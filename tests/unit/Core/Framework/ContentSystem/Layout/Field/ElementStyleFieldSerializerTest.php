<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ElementStyleFieldSerializer::class)]
class ElementStyleFieldSerializerTest extends TestCase
{
    private ElementStyleField $field;

    protected function setUp(): void
    {
        $this->field = new ElementStyleField('style', 'style');
    }

    #[TestDox('deserialize keeps known options and their canonical breakpoints')]
    public function testDeserializeKeepsKnownOptions(): void
    {
        $style = $this->serializer()->deserialize([
            'col-span' => ['md' => 6, 'lg' => 4],
            'align-self' => ['lg' => 'center'],
        ]);

        static::assertSame(
            ['col-span' => ['md' => 6, 'lg' => 4], 'align-self' => ['lg' => 'center']],
            $style->toArray(),
        );
    }

    #[TestDox('deserialize keeps an unknown option verbatim because the read path is registry-free')]
    public function testDeserializeKeepsUnknownOptionVerbatim(): void
    {
        $style = $this->serializer()->deserialize([
            'col-span' => ['md' => 6],
            'removed-plugin-option' => ['md' => 2],
        ]);

        static::assertSame(
            ['col-span' => ['md' => 6], 'removed-plugin-option' => ['md' => 2]],
            $style->toArray(),
        );
    }

    #[TestDox('deserialize drops an unknown breakpoint while keeping the canonical ones')]
    public function testDeserializeDropsUnknownBreakpoint(): void
    {
        $style = $this->serializer()->deserialize(['col-span' => ['md' => 6, 'zz' => 9]]);

        static::assertSame(['col-span' => ['md' => 6]], $style->toArray());
    }

    #[TestDox('deserialize drops an option whose values are all non-scalar')]
    public function testDeserializeDropsNonScalarValues(): void
    {
        $style = $this->serializer()->deserialize(['col-span' => ['md' => ['nested' => 1]]]);

        static::assertTrue($style->isEmpty());
    }

    #[TestDox('deserialize drops an option whose value is a scalar rather than a breakpoint map')]
    public function testDeserializeDropsScalarBreakpointMap(): void
    {
        $style = $this->serializer()->deserialize(['col-span' => 6]);

        static::assertTrue($style->isEmpty());
    }

    #[TestDox('deserialize yields an empty style for an empty map')]
    public function testDeserializeEmpty(): void
    {
        static::assertTrue($this->serializer()->deserialize([])->isEmpty());
    }

    #[TestDox('decode rejects a field that is not an ElementStyleField')]
    public function testDecodeRejectsWrongField(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidFieldType(ElementStyleField::class, StringField::class));

        $this->serializer()->decode(new StringField('x', 'x'), []);
    }

    #[TestDox('decode returns null for a null stored value')]
    public function testDecodeReturnsNullForNull(): void
    {
        static::assertNull($this->serializer()->decode($this->field, null));
    }

    #[TestDox('decode parses a stored JSON string into a populated ElementStyle')]
    public function testDecodeParsesJsonString(): void
    {
        $result = $this->serializer()->decode($this->field, (string) json_encode(['col-span' => ['md' => 6]]));

        static::assertNotNull($result);
        static::assertSame(['col-span' => ['md' => 6]], $result->toArray());
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('validStyleProvider')]
    #[TestDox('accepts $_dataName')]
    public function testAcceptsValidStyle(array $style): void
    {
        static::assertCount(0, $this->validate($style));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validStyleProvider(): iterable
    {
        yield 'an integer span within range' => [['col-span' => ['md' => 6]]];
        yield 'a boolean display set to false' => [['display' => ['xs' => false]]];
        yield 'an enum value from the option vocabulary' => [['align-self' => ['lg' => 'center']]];
        yield 'a string within maxLength' => [['margin' => ['md' => '0 8px']]];
        yield 'an empty style' => [[]];
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('invalidStyleProvider')]
    #[TestDox('rejects $_dataName with a violation at $expectedPath')]
    public function testRejectsInvalidStyle(array $style, string $expectedPath): void
    {
        $violations = $this->validate($style);

        static::assertGreaterThanOrEqual(1, $violations->count());
        // The path proves the violation fires on the offending option/breakpoint, not a stray top-level one
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidStyleProvider(): iterable
    {
        yield 'an unknown option key' => [['unknown-option' => ['md' => 1]], '[unknown-option]'];
        yield 'an unknown breakpoint key' => [['col-span' => ['zz' => 6]], '[col-span][zz]'];
        yield 'an integer outside the declared range' => [['col-span' => ['md' => 99]], '[col-span][md]'];
        yield 'a non-integer value for an integer option' => [['col-span' => ['md' => 'six']], '[col-span][md]'];
        yield 'an enum value outside the vocabulary' => [['align-self' => ['md' => 'sideways']], '[align-self][md]'];
        yield 'a string exceeding maxLength' => [['margin' => ['md' => 'this-value-is-way-too-long']], '[margin][md]'];
    }

    #[TestDox('builds constraints fresh on each call so a changed registry is reflected on the next write')]
    public function testBuildConstraintsDerivesFreshPerCall(): void
    {
        // Reading the registry on every call is the S3(c) contract: an app install/update/activation that
        // changed the option set must take effect on the next write without a process restart.
        $registry = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->exactly(2))->method('all')->willReturn($this->options());

        $serializer = new ElementStyleFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $registry,
            new StyleOptionConstraintDeriver(),
        );

        $serializer->buildConstraints($this->field);
        $serializer->buildConstraints($this->field);
    }

    /**
     * @param array<string, mixed> $style
     */
    private function validate(array $style): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()->getValidator();

        return $validator->validate($style, $this->serializer()->buildConstraints($this->field));
    }

    private function serializer(): ElementStyleFieldSerializer
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        // deserialize() is registry-free; only buildConstraints() reads all().
        $registry->method('all')->willReturn($this->options());

        return new ElementStyleFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $registry,
            new StyleOptionConstraintDeriver(),
        );
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    private function options(): array
    {
        return [
            'col-span' => new StyleOptionSpecification('col-span', new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null), null, 'core'),
            'align-self' => new StyleOptionSpecification('align-self', new StyleOptionValueType('string', ['auto', 'start', 'center'], null, null, 'auto'), null, 'core'),
            'margin' => new StyleOptionSpecification('margin', new StyleOptionValueType('string', null, null, 8, null), null, 'core'),
            'display' => new StyleOptionSpecification('display', new StyleOptionValueType('boolean', null, null, null, null), null, 'core'),
        ];
    }
}
