<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class JsonFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var JsonFieldSerializer
     */
    private $serializer;

    /**
     * @var ConfigJsonField
     */
    private JsonField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(JsonFieldSerializer::class);
        $this->field = new JsonField('data', 'data');

        $definition = $this->registerDefinition(JsonDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public static function encodeProvider(): array
    {
        return [
            [new JsonField('data', 'data'), ['foo' => 'bar'], Json::encode(['foo' => 'bar'])],
            [new JsonField('data', 'data'), ['foo' => 1], Json::encode(['foo' => 1])],
            [new JsonField('data', 'data'), ['foo' => 5.3], Json::encode(['foo' => 5.3])],
            [new JsonField('data', 'data'), ['foo' => ['bar' => 'baz']], Json::encode(['foo' => ['bar' => 'baz']])],

            [new JsonField('data', 'data'), null, null],
            [new JsonField('data', 'data', [], []), null, Json::encode([])],

            [new JsonField('data', 'data', [], ['foo' => 'bar']), null, Json::encode(['foo' => 'bar'])],
            [new JsonField('data', 'data', [], ['foo' => 1]), null, Json::encode(['foo' => 1])],
            [new JsonField('data', 'data', [], ['foo' => 5.3]), null, Json::encode(['foo' => 5.3])],
            [new JsonField('data', 'data', [], ['foo' => ['bar' => 'baz']]), null, Json::encode(['foo' => ['bar' => 'baz']])],
        ];
    }

    /**
     * @dataProvider encodeProvider
     */
    public function testEncode(JsonField $field, $input, $expected): void
    {
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('password', $input, true);
        $actual = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertEquals($expected, $actual);
    }

    public static function decodeProvider(): array
    {
        return [
            [new JsonField('data', 'data'), Json::encode(['foo' => 'bar']), ['foo' => 'bar']],

            [new JsonField('data', 'data'), Json::encode(['foo' => 1]), ['foo' => 1]],
            [new JsonField('data', 'data'), Json::encode(['foo' => 5.3]), ['foo' => 5.3]],
            [new JsonField('data', 'data'), Json::encode(['foo' => ['bar' => 'baz']]), ['foo' => ['bar' => 'baz']]],

            [new JsonField('data', 'data'), null, null],
            [new JsonField('data', 'data', [], []), null, []],

            [new JsonField('data', 'data', [], ['foo' => 'bar']), null, ['foo' => 'bar']],
            [new JsonField('data', 'data', [], ['foo' => 1]), null, ['foo' => 1]],
            [new JsonField('data', 'data', [], ['foo' => 5.3]), null, ['foo' => 5.3]],
            [new JsonField('data', 'data', [], ['foo' => ['bar' => 'baz']]), null, ['foo' => ['bar' => 'baz']]],
        ];
    }

    /**
     * @dataProvider decodeProvider
     */
    public function testDecode(JsonField $field, $input, $expected): void
    {
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $actual = $this->serializer->decode($field, $input);
        static::assertEquals($expected, $actual);
    }

    public function testEmptyValueForRequiredField(): void
    {
        $field = new JsonField('data', 'data');
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('data', [], true);

        $result = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertEquals('[]', $result);
    }

    public function testRequiredValidationThrowsError(): void
    {
        $field = (new JsonField('data', 'data'))->addFlags(new ApiAware(), new Required());
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('data', null, true);

        /** @var WriteConstraintViolationException|null $exception */
        $exception = null;

        try {
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'JsonFieldSerializer does not throw violation exception for empty required field.');
        static::assertEquals('/data', $exception->getViolations()->get(0)->getPropertyPath());
    }

    public function testNullValueForNotRequiredField(): void
    {
        $field = new JsonField('data', 'data');
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('data', null, true);

        $result = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertNull($result);
    }

    public function testIgnoresInvalidUtf8Characters(): void
    {
        $result = Json::encode("something\x82 another");

        static::assertEquals('"something another"', $result);
    }
}
