<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ListFieldSerializer::class)]
class ListFieldSerializerTest extends TestCase
{
    /**
     * @param array<string>|null $keyValue
     */
    #[DataProvider('encodeProvider')]
    public function testCanEncodeListField(?string $fieldType, ?array $keyValue, ?string $expected): void
    {
        $serializer = new ListFieldSerializer(
            $this->createMock(ValidatorInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        $result = iterator_to_array(
            $serializer->encode(
                new ListField('testStorage', 'testProperty', $fieldType),
                EntityExistence::createEmpty(),
                new KeyValuePair('testStorage', $keyValue, true),
                $this->createMock(WriteParameterBag::class)
            )
        );

        static::assertArrayHasKey('testStorage', $result);
        static::assertSame($expected, $result['testStorage']);
    }

    public static function encodeProvider(): \Generator
    {
        yield 'field type is specified, validateTypes() is visited' => [
            'fieldType' => StringField::class,
            'keyValue' => ['testValue'],
            'expected' => Json::encode(['testValue']),
        ];
        yield 'validateTypes() is skipped' => [
            'fieldType' => null,
            'keyValue' => ['testValue2'],
            'expected' => Json::encode(['testValue2']),
        ];
        yield 'everything is skipped, no encoding' => [
            'fieldType' => null,
            'keyValue' => null,
            'expected' => null,
        ];
    }

    public function testEncodeThrowsExceptionWithUnsupportedField(): void
    {
        $serializer = new ListFieldSerializer(
            $this->createMock(ValidatorInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        // ListFieldSerializer only supports ListField, so we create an unsupported field type
        $field = new IdField('test', 'test');

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(ListField::class, $field));
        iterator_to_array(
            $serializer->encode(
                $field,
                $this->createMock(EntityExistence::class),
                $this->createMock(KeyValuePair::class),
                $this->createMock(WriteParameterBag::class)
            )
        );
    }

    /**
     * @param array<mixed>|null $expected
     */
    #[DataProvider('decodeProvider')]
    public function testDecode(ListField $field, ?string $input, ?array $expected): void
    {
        $serializer = new ListFieldSerializer(
            $this->createMock(ValidatorInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class)
        );

        $actual = $serializer->decode($field, $input);
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{0: ListField, 1: string|null, 2: array<mixed>|null}>
     */
    public static function decodeProvider(): iterable
    {
        yield 'associative JSON object is decoded to list values' => [new ListField('data', 'data'), Json::encode(['foo' => 'bar']), ['bar']];
        yield 'JSON list is decoded unchanged' => [new ListField('data', 'data'), Json::encode([0 => 'bar', 1 => 'foo']), ['bar', 'foo']];
        yield 'numeric JSON value is decoded to list value' => [new ListField('data', 'data'), Json::encode(['foo' => 1]), [1]];
        yield 'float JSON value is decoded to list value' => [new ListField('data', 'data'), Json::encode(['foo' => 5.3]), [5.3]];
        yield 'nested JSON object is decoded to list value' => [new ListField('data', 'data'), Json::encode(['foo' => ['bar' => 'baz']]), [['bar' => 'baz']]];
        yield 'null value stays null' => [new ListField('data', 'data'), null, null];
    }
}
