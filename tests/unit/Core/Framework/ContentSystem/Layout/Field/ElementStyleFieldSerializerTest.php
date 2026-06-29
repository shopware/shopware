<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\ConstraintViolationList;
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

    /**
     * @param array<string, mixed> $input
     * @param array<string, scalar|array<string, scalar>> $expected
     */
    #[DataProvider('deserializeProvider')]
    #[TestDox('$_dataName')]
    public function testDeserialize(array $input, array $expected): void
    {
        static::assertSame($expected, $this->serializer()->deserialize($input)->toArray());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, scalar|array<string, scalar>>}>
     */
    public static function deserializeProvider(): iterable
    {
        yield 'keeps known options and their canonical breakpoints' => [
            ['col-span' => ['md' => 6, 'lg' => 4], 'align-self' => ['lg' => 'center']],
            ['col-span' => ['md' => 6, 'lg' => 4], 'align-self' => ['lg' => 'center']],
        ];

        yield 'keeps an unknown option verbatim because the read path is registry-free' => [
            ['col-span' => ['md' => 6], 'removed-plugin-option' => ['md' => 2]],
            ['col-span' => ['md' => 6], 'removed-plugin-option' => ['md' => 2]],
        ];

        yield 'drops an unknown breakpoint while keeping the canonical ones' => [
            ['col-span' => ['md' => 6, 'zz' => 9]],
            ['col-span' => ['md' => 6]],
        ];

        yield 'drops an option whose values are all non-scalar' => [
            ['col-span' => ['md' => ['nested' => 1]]],
            [],
        ];

        yield 'keeps a flat scalar value verbatim' => [
            ['col-span' => 6],
            ['col-span' => 6],
        ];

        yield 'keeps a flat boolean false verbatim' => [
            ['flat-flag' => false],
            ['flat-flag' => false],
        ];

        yield 'yields an empty style for an empty map' => [
            [],
            [],
        ];
    }

    #[TestDox('decode parses a stored JSON string into a populated ElementStyle')]
    public function testDecodeParsesJsonString(): void
    {
        $result = $this->serializer()->decode($this->field, (string) json_encode(['col-span' => ['md' => 6]]));

        static::assertNotNull($result);
        static::assertSame(['col-span' => ['md' => 6]], $result->toArray());
    }

    #[TestDox('decode returns null for a null stored value')]
    public function testDecodeReturnsNullForNull(): void
    {
        static::assertNull($this->serializer()->decode($this->field, null));
    }

    #[TestDox('encode yields the JSON-encoded array for an ElementStyle value')]
    public function testEncodeYieldsJsonForElementStyle(): void
    {
        $pair = new KeyValuePair('style', new ElementStyle(['col-span' => ['md' => 6]]), false);

        $result = iterator_to_array(
            $this->encodingSerializer()->encode($this->field, $this->existence(), $pair, $this->parameters())
        );

        static::assertArrayHasKey('style', $result);
        static::assertIsString($result['style']);
        static::assertSame(['col-span' => ['md' => 6]], json_decode($result['style'], true, 512, \JSON_THROW_ON_ERROR));
    }

    #[TestDox('encode yields null for a null value')]
    public function testEncodeYieldsNullForNull(): void
    {
        $pair = new KeyValuePair('style', null, false);

        $result = iterator_to_array(
            $this->encodingSerializer()->encode($this->field, $this->existence(), $pair, $this->parameters())
        );

        static::assertArrayHasKey('style', $result);
        static::assertNull($result['style']);
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('acceptsValidStyleProvider')]
    #[TestDox('accepts $_dataName')]
    public function testAcceptsValidStyle(array $style): void
    {
        static::assertCount(0, $this->validate($style));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsValidStyleProvider(): iterable
    {
        yield 'an integer span within range' => [['col-span' => ['md' => 6]]];
        yield 'a boolean display set to false' => [['display' => ['xs' => false]]];
        yield 'an enum value from the option vocabulary' => [['align-self' => ['lg' => 'center']]];
        yield 'a string within maxLength' => [['margin' => ['md' => '0 8px']]];
        yield 'an empty style' => [[]];
        yield 'a flat integer option sent as a scalar' => [['z-index' => 10]];
        yield 'a flat string option within its maxLength' => [['flat-label' => 'short']];
        yield 'coexisting flat and breakpoint-aware options' => [['z-index' => 10, 'col-span' => ['md' => 6]]];
    }

    #[TestDox('builds constraints fresh on each call so a changed registry is reflected on the next write')]
    public function testBuildConstraintsDerivesFreshPerCall(): void
    {
        // An app install/update/activation that changed the option set must take effect
        // on the next write without a process restart, so each call re-reads the registry (never memoizes).
        // assertCount(0, $afterChange) below already fails if constraints are memoized, so the call
        // count needs no separate expectation; a stub returning a changed set on the second call suffices.
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturnOnConsecutiveCalls(
            ['col-span' => new StyleOptionSpecification('col-span', new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null), true, null, 'core')],
            ['display' => new StyleOptionSpecification('display', new StyleOptionValueType('boolean', null, null, null, null), true, null, 'core')],
        );

        $serializer = new ElementStyleFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $registry,
            new StyleOptionConstraintDeriver(),
        );

        $validator = Validation::createValidatorBuilder()->getValidator();
        $style = ['display' => ['xs' => false]];
        // The first registry has no `display` option (the write is rejected); after the registry changes to
        // include it, the very next buildConstraints() accepts the same write.
        $beforeChange = $validator->validate($style, $serializer->buildConstraints($this->field));
        $afterChange = $validator->validate($style, $serializer->buildConstraints($this->field));

        static::assertGreaterThanOrEqual(1, $beforeChange->count());
        static::assertCount(0, $afterChange);
    }

    #[TestDox('decode rejects a field that is not an ElementStyleField')]
    public function testDecodeRejectsWrongField(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidFieldType(ElementStyleField::class, StringField::class));

        $this->serializer()->decode(new StringField('x', 'x'), []);
    }

    #[TestDox('encode throws an invalid value type for a non-array non-ElementStyle value')]
    public function testEncodeThrowsForInvalidValueType(): void
    {
        $pair = new KeyValuePair('style', 42, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('style', 'array|ElementStyle', 'integer')
        );

        iterator_to_array(
            $this->encodingSerializer()->encode($this->field, $this->existence(), $pair, $this->parameters())
        );
    }

    #[TestDox('encode rejects a field that is not an ElementStyleField')]
    public function testEncodeRejectsWrongField(): void
    {
        $pair = new KeyValuePair('style', null, false);

        $this->expectExceptionObject(ContentSystemException::invalidFieldType(ElementStyleField::class, StringField::class));

        iterator_to_array(
            $this->encodingSerializer()->encode(new StringField('x', 'x'), $this->existence(), $pair, $this->parameters())
        );
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('rejectsInvalidStyleProvider')]
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
    public static function rejectsInvalidStyleProvider(): iterable
    {
        yield 'an unknown option key' => [['unknown-option' => ['md' => 1]], '[unknown-option]'];
        yield 'an empty breakpoint map' => [['col-span' => []], '[col-span]'];
        yield 'an unknown breakpoint key' => [['col-span' => ['zz' => 6]], '[col-span][zz]'];
        yield 'an integer outside the declared range' => [['col-span' => ['md' => 99]], '[col-span][md]'];
        yield 'a non-integer value for an integer option' => [['col-span' => ['md' => 'six']], '[col-span][md]'];
        yield 'an enum value outside the vocabulary' => [['align-self' => ['md' => 'sideways']], '[align-self][md]'];
        yield 'a string exceeding maxLength' => [['margin' => ['md' => 'this-value-is-way-too-long']], '[margin][md]'];
        yield 'a flat integer option sent as a breakpoint map' => [['z-index' => ['md' => 10]], '[z-index]'];
        yield 'a breakpoint-aware option sent as a bare scalar' => [['col-span' => 6], '[col-span]'];
        yield 'a flat string option exceeding its maxLength' => [['flat-label' => '123456789'], '[flat-label]'];
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
     * encode() runs validateIfNeeded before serializing; a passthrough validator lets the contract tests
     * exercise the encode branches without composing the full constraint set.
     */
    private function encodingSerializer(): ElementStyleFieldSerializer
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn($this->options());

        return new ElementStyleFieldSerializer(
            $validator,
            static::createStub(DefinitionInstanceRegistry::class),
            $registry,
            new StyleOptionConstraintDeriver(),
        );
    }

    private function existence(): EntityExistence
    {
        return new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
    }

    private function parameters(): WriteParameterBag
    {
        return static::createStub(WriteParameterBag::class);
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    private function options(): array
    {
        return [
            'col-span' => new StyleOptionSpecification('col-span', new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null), true, null, 'core'),
            'align-self' => new StyleOptionSpecification('align-self', new StyleOptionValueType('string', ['auto', 'start', 'center'], null, null, 'auto'), true, null, 'core'),
            'margin' => new StyleOptionSpecification('margin', new StyleOptionValueType('string', null, null, 8, null), true, null, 'core'),
            'display' => new StyleOptionSpecification('display', new StyleOptionValueType('boolean', null, null, null, null), true, null, 'core'),
            'z-index' => new StyleOptionSpecification('z-index', new StyleOptionValueType('integer', null, null, null, null), false, null, 'core'),
            'flat-label' => new StyleOptionSpecification('flat-label', new StyleOptionValueType('string', null, null, 8, null), false, null, 'core'),
        ];
    }
}
