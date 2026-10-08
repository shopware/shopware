<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformance;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformanceValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PropertyTypeConformanceValidator::class)]
class PropertyTypeConformanceValidatorTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('acceptsConformingElementProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsAConformingElement(array $properties): void
    {
        static::assertCount(0, $this->validate($this->element($properties)));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function acceptsConformingElementProvider(): iterable
    {
        yield 'a string under a string declaration' => [['headline' => 'Hi']];
        yield 'an integer under an integer declaration' => [['count' => 5]];
        yield 'an integer under a number declaration' => [['ratio' => 5]];
        yield 'a float under a number declaration' => [['ratio' => 1.5]];
        yield 'a boolean under a boolean declaration' => [['featured' => true]];
        yield 'a null under a string declaration' => [['headline' => null]];
        yield 'a value matching the first member of an all-primitive union' => [['spread' => 'wide']];
        yield 'a value matching the second member of an all-primitive union' => [['spread' => 3]];
        yield 'an array under a bare object declaration' => [['config' => ['nested' => 'value']]];
        yield 'a value matching no member of a union carrying object' => [['columns' => 'not-an-integer']];
        yield 'a value under a key the type does not declare' => [['mediaId' => ['not', 'a', 'string']]];
        yield 'an element carrying no properties at all' => [[]];
        // A map under a string declaration passes only through the conformance predicate on the declared type;
        // a private match table would reject it.
        yield 'a single-entry anchor map under a translatable declaration' => [['text' => [Defaults::LANGUAGE_SYSTEM => 'Hallo']]];
        // A map with a second language key passes only through the conformance predicate on the declared type;
        // a private match table would reject it.
        yield 'a multi-entry language map under a translatable declaration' => [['text' => [Defaults::LANGUAGE_SYSTEM => 'Hallo', Uuid::randomHex() => 'Ciao']]];
    }

    #[TestDox('reports one violation per disagreeing key rather than one for the element')]
    public function testReportsOneViolationPerDisagreeingKey(): void
    {
        $violations = $this->validate($this->element(['headline' => 42, 'count' => 'five', 'featured' => true]));

        static::assertCount(2, $violations);
        static::assertSame('[properties][headline]', $violations->get(0)->getPropertyPath());
        static::assertSame('[properties][count]', $violations->get(1)->getPropertyPath());
    }

    #[DataProvider('ignoresMalformedElementProvider')]
    #[TestDox('reports no violation for an element with $_dataName')]
    public function testIgnoresAMalformedElement(mixed $element): void
    {
        static::assertCount(0, $this->validate($element));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ignoresMalformedElementProvider(): iterable
    {
        yield 'a non-string component' => [['component' => 7, 'properties' => ['headline' => 42]]];
        yield 'a missing component' => [['properties' => ['headline' => 42]]];
        yield 'non-array properties' => [['component' => 'Sw:Block', 'properties' => 'oops']];
    }

    #[TestDox('reports one violation per non-language key, and none for a language key beside them')]
    public function testReportsOneViolationPerNonLanguageKey(): void
    {
        $violations = $this->validate($this->element(['text' => [Defaults::LANGUAGE_SYSTEM => 'Hallo', 'de-DE' => 'Hallo', 'en-GB' => 'Hi']]));

        static::assertCount(2, $violations);
        static::assertStringContainsString('"de-DE" is not', (string) $violations->get(0)->getMessage());
        static::assertStringContainsString('"en-GB" is not', (string) $violations->get(1)->getMessage());
    }

    #[TestDox('reports nothing, and never reaches the throwing lookup, for a component the registry does not know')]
    public function testEmitsNothingForAnUnregisteredComponent(): void
    {
        // get() throws the way both concrete registries do, so a regression from has()-guarded to unguarded
        // surfaces as the 404 escaping the constraint pass rather than as a missing violation.
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->method('get')->willThrowException(ContentSystemException::elementTypeNotFound('Sw:Ghost'));

        $element = ['id' => 'el-1', 'component' => 'Sw:Ghost', 'properties' => ['headline' => 42]];

        static::assertCount(0, $this->validate($element, $registry));
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('rejectsNonConformingElementProvider')]
    #[TestDox('reports one violation naming the declared and actual type for $_dataName')]
    public function testRejectsANonConformingElement(array $properties, string $key, string $declaredType, string $actualType): void
    {
        $violations = $this->validate($this->element($properties));

        static::assertCount(1, $violations);
        static::assertSame('[properties][' . $key . ']', $violations->get(0)->getPropertyPath());
        static::assertSame(
            \sprintf('Property "%s" is declared as "%s" but carries a value of type "%s".', $key, $declaredType, $actualType),
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, string, string}>
     */
    public static function rejectsNonConformingElementProvider(): iterable
    {
        yield 'a non-string value under a string declaration' => [['headline' => ['a', 'b']], 'headline', 'string', 'array'];
        yield 'a non-integer value under an integer declaration' => [['count' => 'five'], 'count', 'integer', 'string'];
        yield 'a string under a number declaration' => [['ratio' => '1.5'], 'ratio', 'number', 'string'];
        yield 'an integer under a boolean declaration' => [['featured' => 1], 'featured', 'boolean', 'int'];
        yield 'a value matching no member of an all-primitive union' => [['spread' => true], 'spread', 'string|integer', 'bool'];
        // Proves the violation is built from PropertyType::admits(): a surviving private match table would
        // judge a bare string under a translatable declaration with a different message.
        yield 'a bare string under a translatable declaration' => [['text' => 'Hallo'], 'text', 'string (translatable)', 'string'];
        // Pins the violation a client reads for an empty map: the declared type, the actual type and the path,
        // none of which the predicate's boolean verdict expresses.
        yield 'an empty language map, refused because absence rather than an empty map means no translations' => [['text' => []], 'text', 'string (translatable)', 'array'];
        // A map entry that fails the type check is reported once as a type violation, not again for its non-language key.
        yield 'a language map with a non-string entry under a non-language key, reported once as a type violation' => [['text' => ['de-DE' => 5]], 'text', 'string (translatable)', 'array'];
    }

    #[DataProvider('rejectsLanguageKeyProvider')]
    #[TestDox('reports one violation naming the offending key for $_dataName')]
    public function testRejectsANonLanguageMapKey(string $languageKey): void
    {
        $violations = $this->validate($this->element(['text' => [$languageKey => 'Hallo']]));

        static::assertCount(1, $violations);
        static::assertSame('[properties][text]', $violations->get(0)->getPropertyPath());
        static::assertSame(
            \sprintf(
                'Property "text" is translatable, so every key of its value must be a language id in lowercase UUID hex; "%s" is not.',
                $languageKey
            ),
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsLanguageKeyProvider(): iterable
    {
        yield 'an upper-case UUID hex key' => [strtoupper(Defaults::LANGUAGE_SYSTEM)];
        yield 'a key that is not UUID hex at all' => ['de-DE'];
        yield 'a UUID hex key one character short' => [substr(Defaults::LANGUAGE_SYSTEM, 0, 31)];
    }

    /**
     * @param array<array-key, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function element(array $properties): array
    {
        return ['id' => 'el-1', 'component' => 'Sw:Block', 'properties' => $properties];
    }

    private function validate(mixed $element, ?AbstractContentSystemElementTypeRegistry $registry = null): ConstraintViolationListInterface
    {
        $validator = new PropertyTypeConformanceValidator($registry ?? $this->registry());

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PropertyTypeConformanceValidator::class => $validator,
            ]))
            ->getValidator()
            ->validate($element, new PropertyTypeConformance());
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = ['Sw:Block' => new ContentSystemElementTypeSpecification(
            'Sw:Block',
            'Block',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'headline' => $this->property('string'),
                'count' => $this->property('integer'),
                'ratio' => $this->property('number'),
                'featured' => $this->property('boolean'),
                'spread' => $this->property(['string', 'integer']),
                'columns' => $this->property(['integer', 'object']),
                'config' => $this->property('object'),
                'text' => $this->property('string', translatable: true),
            ],
            [],
        )];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param string|list<string> $type
     */
    private function property(string|array $type, bool $translatable = false): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, $translatable, null, null), false, '', '', null);
    }
}
