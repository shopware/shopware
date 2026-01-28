<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AbstractFieldSerializer::class)]
class AbstractFieldSerializerTest extends TestCase
{
    public function testGetConstraintsOnlyCalledOnce(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $serializer = new TestFieldSerializer(
            $validator,
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        static::assertSame(0, $serializer->getConstraintsCallCounter);
        $entityExistence = new EntityExistence('test', ['id' => Uuid::randomHex()], true, false, false, []);
        $field = new StringField('test', 'test');

        $data = new KeyValuePair('foo', 'bar', true);

        static::assertNotNull($serializer->encode($field, $entityExistence, $data, $this->createMock(WriteParameterBag::class))->current());
        static::assertSame(1, $serializer->getConstraintsCallCounter);

        static::assertNotNull($serializer->encode($field, $entityExistence, $data, $this->createMock(WriteParameterBag::class))->current());
        static::assertSame(1, $serializer->getConstraintsCallCounter);
    }

    public function testCaching(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $serializer = new TestFieldSerializer(
            $validator,
            $this->createMock(DefinitionInstanceRegistry::class)
        );
        $parameters = $this->createMock(WriteParameterBag::class);

        static::assertSame(0, $serializer->getConstraintsCallCounter);
        $entityExistence = new EntityExistence('test', ['id' => Uuid::randomHex()], true, false, false, []);

        $data = new KeyValuePair('foo', 'bar', true);
        $field = new StringField('test', 'test');
        static::assertNotNull($serializer->encode($field, $entityExistence, $data, $parameters)->current());
        static::assertSame(1, $serializer->getConstraintsCallCounter);

        $serializer->getConstraintsCallCounter = 0;
        $newField = new StringField('test', 'test');
        // a different field object should not return the cached constraints of the other field
        static::assertNotNull($serializer->encode($newField, $entityExistence, $data, $parameters)->current());
        static::assertSame(1, $serializer->getConstraintsCallCounter);
    }

    public function testNormalizeReturnsDataUnchanged(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $serializer = new TestFieldSerializer(
            $validator,
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        $field = new StringField('test', 'test');
        $data = ['foo' => 'bar', 'baz' => 'qux'];
        $parameters = $this->createMock(WriteParameterBag::class);

        $result = $serializer->normalize($field, $data, $parameters);

        static::assertSame($data, $result);
    }

    #[DataProvider('requiresValidationProvider')]
    public function testRequiresValidation(
        ?string $value,
        bool $isChild,
        bool $hasInheritedFlag,
        bool $isTranslation,
        bool $hasRequiredFlag,
        bool $expected
    ): void {
        $primaryKey = ['id' => Uuid::randomHex()];

        $field = new StringField('test', 'test');
        if ($hasInheritedFlag) {
            $field->addFlags(new Inherited());
        }
        if ($hasRequiredFlag) {
            $field->addFlags(new Required());
        }

        $entityName = $isTranslation ? 'test_translation' : 'test';
        $existence = new EntityExistence($entityName, $primaryKey, true, $isChild, false, []);

        $parameters = $this->createMock(WriteParameterBag::class);
        $parameters->method('getPath')->willReturn($hasInheritedFlag ? '/test' : '');
        $parameters->method('getContext')->willReturn(WriteContext::createFromContext(Context::createDefaultContext()));
        $parameters->method('getCurrentWriteLanguageId')->willReturn(Uuid::randomHex());

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        if ($isTranslation) {
            $translationDefinition = $this->createMock(EntityTranslationDefinition::class);
            $registry->method('getByEntityName')->willReturn($translationDefinition);
        } else {
            $entityDefinition = $this->createMock(EntityDefinition::class);
            $registry->method('getByEntityName')->willReturn($entityDefinition);
        }

        $validator = $this->createMock(ValidatorInterface::class);
        $serializer = new TestFieldSerializer($validator, $registry);

        $result = $serializer->publicRequiresValidation($field, $existence, $value, $parameters);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{string|null, bool, bool, bool, bool, bool}>
     */
    public static function requiresValidationProvider(): iterable
    {
        yield 'When value is not null, validation is always required' => [
            'some value',
            false,
            false,
            false,
            false,
            true,
        ];
        yield 'When entity is child and field is inherited, validation is not required' => [
            null,
            true,
            true,
            false,
            false,
            false,
        ];
        yield 'When value is null, entity is translation and language is not system, validation is not required' => [
            null,
            false,
            false,
            true,
            false,
            false,
        ];
        yield 'When value is null and field is required, validation is required' => [
            null,
            false,
            false,
            false,
            true,
            true,
        ];
        yield 'When value is null and field is not required, validation is not required' => [
            null,
            false,
            false,
            false,
            false,
            false,
        ];
    }
}

/**
 * @internal
 */
class TestFieldSerializer extends AbstractFieldSerializer
{
    public int $getConstraintsCallCounter = 0;

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $data->getKey() => $data->getValue();
    }

    public function decode(Field $field, mixed $value): mixed
    {
        return $value;
    }

    public function publicRequiresValidation(Field $field, EntityExistence $existence, mixed $value, WriteParameterBag $parameters): bool
    {
        return $this->requiresValidation($field, $existence, $value, $parameters);
    }

    protected function getConstraints(Field $field): array
    {
        ++$this->getConstraintsCallCounter;

        return [new NotBlank()];
    }
}
