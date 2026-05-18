<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(JsonFieldSerializer::class)]
class JsonFieldSerializerTest extends TestCase
{
    private JsonFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = Validation::createValidator();
        $this->serializer = new JsonFieldSerializer($validator, $this->definitionRegistry);
        $dateSerializer = new DateFieldSerializer($validator, $this->definitionRegistry);
        $dateTimeSerializer = new DateTimeFieldSerializer($validator, $this->definitionRegistry);

        $this->definitionRegistry
            ->method('getSerializer')
            ->willReturnCallback(fn (string $class) => match ($class) {
                DateFieldSerializer::class => $dateSerializer,
                DateTimeFieldSerializer::class => $dateTimeSerializer,
                JsonFieldSerializer::class => $this->serializer,
                default => throw new \LogicException(\sprintf('Unexpected serializer "%s".', $class)),
            });
    }

    /**
     * @param array<string, mixed>|null $input
     */
    #[DataProvider('encodeProvider')]
    public function testEncode(JsonField $field, ?array $input, ?string $expected): void
    {
        $field->compile($this->definitionRegistry);

        $actual = $this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('data', $input, true),
            $this->createWriteParameterBag()
        )->current();

        static::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{JsonField, array<string, mixed>|null, string|null}>
     */
    public static function encodeProvider(): array
    {
        return [
            'string value' => [new JsonField('data', 'data'), ['foo' => 'bar'], Json::encode(['foo' => 'bar'])],
            'integer value' => [new JsonField('data', 'data'), ['foo' => 1], Json::encode(['foo' => 1])],
            'float value' => [new JsonField('data', 'data'), ['foo' => 5.3], Json::encode(['foo' => 5.3])],
            'nested value' => [new JsonField('data', 'data'), ['foo' => ['bar' => 'baz']], Json::encode(['foo' => ['bar' => 'baz']])],
            'null without default' => [new JsonField('data', 'data'), null, null],
            'null with empty default' => [new JsonField('data', 'data', [], []), null, Json::encode([])],
            'null with string default' => [new JsonField('data', 'data', [], ['foo' => 'bar']), null, Json::encode(['foo' => 'bar'])],
            'null with integer default' => [new JsonField('data', 'data', [], ['foo' => 1]), null, Json::encode(['foo' => 1])],
            'null with float default' => [new JsonField('data', 'data', [], ['foo' => 5.3]), null, Json::encode(['foo' => 5.3])],
            'null with nested default' => [new JsonField('data', 'data', [], ['foo' => ['bar' => 'baz']]), null, Json::encode(['foo' => ['bar' => 'baz']])],
        ];
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('decodeProvider')]
    public function testDecode(JsonField $field, ?string $input, ?array $expected): void
    {
        $field->compile($this->definitionRegistry);

        static::assertSame($expected, $this->serializer->decode($field, $input));
    }

    /**
     * @return array<string, array{JsonField, string|null, array<string, mixed>|null}>
     */
    public static function decodeProvider(): array
    {
        return [
            'string value' => [new JsonField('data', 'data'), Json::encode(['foo' => 'bar']), ['foo' => 'bar']],
            'integer value' => [new JsonField('data', 'data'), Json::encode(['foo' => 1]), ['foo' => 1]],
            'float value' => [new JsonField('data', 'data'), Json::encode(['foo' => 5.3]), ['foo' => 5.3]],
            'nested value' => [new JsonField('data', 'data'), Json::encode(['foo' => ['bar' => 'baz']]), ['foo' => ['bar' => 'baz']]],
            'null without default' => [new JsonField('data', 'data'), null, null],
            'null with empty default' => [new JsonField('data', 'data', [], []), null, []],
            'null with string default' => [new JsonField('data', 'data', [], ['foo' => 'bar']), null, ['foo' => 'bar']],
            'null with integer default' => [new JsonField('data', 'data', [], ['foo' => 1]), null, ['foo' => 1]],
            'null with float default' => [new JsonField('data', 'data', [], ['foo' => 5.3]), null, ['foo' => 5.3]],
            'null with nested default' => [new JsonField('data', 'data', [], ['foo' => ['bar' => 'baz']]), null, ['foo' => ['bar' => 'baz']]],
        ];
    }

    public function testEmptyArrayValueIsEncodedForRequiredField(): void
    {
        $result = $this->serializer->encode(
            new JsonField('data', 'data'),
            EntityExistence::createEmpty(),
            new KeyValuePair('data', [], true),
            $this->createWriteParameterBag()
        )->current();

        static::assertSame('[]', $result);
    }

    public function testRequiredValidationThrowsError(): void
    {
        $field = (new JsonField('data', 'data'))->addFlags(new Required());

        try {
            $this->serializer->encode(
                $field,
                EntityExistence::createEmpty(),
                new KeyValuePair('data', null, true),
                $this->createWriteParameterBag()
            )->current();

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/data', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testNullValueForNotRequiredField(): void
    {
        $result = $this->serializer->encode(
            new JsonField('data', 'data'),
            EntityExistence::createEmpty(),
            new KeyValuePair('data', null, true),
            $this->createWriteParameterBag()
        )->current();

        static::assertNull($result);
    }

    public function testNestedJsonDateFieldsAreEncodedWithStorageFormats(): void
    {
        $field = new JsonField('root', 'root', [
            new JsonField('child', 'child', [
                new DateTimeField('childDateTime', 'childDateTime'),
                new DateField('childDate', 'childDate'),
            ]),
        ]);
        $field->compile($this->definitionRegistry);

        $insertTime = new \DateTimeImmutable('2004-02-29 08:59:59.001');

        $payload = iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('root', [
                'child' => [
                    'childDateTime' => $insertTime,
                    'childDate' => $insertTime,
                ],
            ], true),
            $this->createWriteParameterBag()
        ));

        static::assertArrayHasKey('root', $payload);
        static::assertIsString($payload['root']);

        $decoded = json_decode($payload['root'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($insertTime->format(Defaults::STORAGE_DATE_TIME_FORMAT), $decoded['child']['childDateTime']);
        static::assertSame($insertTime->format(Defaults::STORAGE_DATE_FORMAT), $decoded['child']['childDate']);
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
