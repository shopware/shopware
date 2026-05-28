<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StringFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class StringFieldSerializerTest extends TestCase
{
    private StringFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private RecursiveValidator $validator;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = new RecursiveValidator(
            new ExecutionContextFactory($this->createMock(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        $this->serializer = new StringFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
            $sanitizer
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b']));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'c', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 'c'], $encoded);
    }

    public function testChoiceStrictAcceptsValidValue(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b'], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'a', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 'a'], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b'], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'c', true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 'c'),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('requiredValueProvider')]
    public function testRequiredFieldsRejectMissingAndBlankValues(?string $input, array $flags): void
    {
        $field = $this->createField($flags);

        // Create case
        try {
            $this->encodeValue($field, $input);
            static::fail('Required string fields must reject missing or blank values for new entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }

        // Update case
        try {
            $this->encodeValue($field, $input, exists: true);
            static::fail('Required string fields must reject missing or blank values for existing entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    /**
     * @return iterable<string, array{?string, list<Flag>}>
     */
    public static function requiredValueProvider(): iterable
    {
        yield 'required field rejects null' => [null, [new Required()]];
        yield 'required field rejects null even when empty string is allowed' => [null, [new Required(), new AllowEmptyString()]];
        yield 'required field rejects empty string' => ['', [new Required()]];
        yield 'required field rejects whitespace-only value' => [' ', [new Required()]];
        yield 'required field rejects HTML-only content after stripping tags' => ['<test>', [new Required()]];
    }

    #[DataProvider('optionalBlankValueProvider')]
    public function testOptionalFieldsNormalizeBlankValuesToNull(?string $input): void
    {
        $field = $this->createField();

        // Create case
        static::assertSame(['name' => null], $this->encodeValue($field, $input));

        // Update case
        static::assertSame(['name' => null], $this->encodeValue($field, $input, exists: true));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function optionalBlankValueProvider(): iterable
    {
        yield 'optional field normalizes null to null' => [null];
        yield 'optional field normalizes empty string to null' => [''];
        yield 'optional field normalizes whitespace-only value to null' => [' '];
    }

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('allowedEmptyValueProvider')]
    public function testAllowedEmptyValuesArePreserved(string $input, string $expected, array $flags): void
    {
        $field = $this->createField($flags);

        // Create case
        static::assertSame(['name' => $expected], $this->encodeValue($field, $input));

        // Update case
        static::assertSame(['name' => $expected], $this->encodeValue($field, $input, exists: true));
    }

    /**
     * @return iterable<string, array{string, string, list<Flag>}>
     */
    public static function allowedEmptyValueProvider(): iterable
    {
        yield 'allow empty preserves whitespace-only value' => [' ', ' ', [new AllowEmptyString()]];
        yield 'required field with allow empty preserves empty string' => ['', '', [new Required(), new AllowEmptyString()]];
    }

    public function testMaxLengthViolationThrowsConstraintViolation(): void
    {
        $field = $this->createField(maxLength: 5);

        // Create case
        try {
            $this->encodeValue($field, '123456789');
            static::fail('String fields must reject values that exceed their max length for new entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }

        // Update case
        try {
            $this->encodeValue($field, '123456789', exists: true);
            static::fail('String fields must reject values that exceed their max length for existing entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testNonStringValueThrowsConstraintViolation(): void
    {
        $field = $this->createField([new Required()]);

        // Create case
        try {
            $this->encodeValue($field, true);
            static::fail('String fields must reject non-string values for new entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }

        // Update case
        try {
            $this->encodeValue($field, true, exists: true);
            static::fail('String fields must reject non-string values for existing entities.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('stringValueProvider')]
    public function testStringValuesAreEncoded(string $input, string $expected, array $flags): void
    {
        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturn($expected);
        $this->serializer = new StringFieldSerializer($this->validator, $this->definitionInstanceRegistry, $sanitizer);

        $field = $this->createField($flags);

        // Create case
        static::assertSame(['name' => $expected], $this->encodeValue($field, $input));

        // Update case
        static::assertSame(['name' => $expected], $this->encodeValue($field, $input, exists: true));
    }

    /**
     * @return iterable<string, array{string, string, list<Flag>}>
     */
    public static function stringValueProvider(): iterable
    {
        yield 'string is passed through' => ['test12-B', 'test12-B', [new Required()]];
        yield 'HTML is kept when sanitizing is disabled' => ['<test>', '<test>', [new Required(), new AllowHtml(false)]];
        yield 'sanitized HTML strips script tag' => ['<script></script>test12-B', 'test12-B', [new Required(), new AllowHtml()]];
    }

    /**
     * @param list<Flag> $flags
     */
    private function createField(array $flags = [], ?int $maxLength = null): StringField
    {
        $field = new StringField('name', 'name', $maxLength ?? 255);
        $field->addFlags(...$flags);

        return $field;
    }

    /**
     * @return array<string, string|null>
     */
    private function encodeValue(StringField $field, bool|string|null $value, bool $exists = false): array
    {
        $existence = new EntityExistence(null, [], $exists, false, false, []);
        $kv = new KeyValuePair('name', $value, true);

        return iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
