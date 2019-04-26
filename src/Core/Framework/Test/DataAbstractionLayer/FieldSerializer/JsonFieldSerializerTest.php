<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class JsonFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour, CacheTestBehaviour;

    /**
     * @var ConfigJsonFieldSerializer
     */
    private $serializer;

    /**
     * @var ConfigJsonField
     */
    private $field;

    /**
     * @var EntityExistence
     */
    private $existence;

    /**
     * @var WriteParameterBag
     */
    private $parameters;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(JsonFieldSerializer::class);
        $this->field = new JsonField('data', 'data');

        $this->existence = new EntityExistence(JsonDefinition::class, [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            JsonDefinition::class,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue(),
            new FieldExceptionStack()
        );
    }

    public function encodeProvider(): array
    {
        return [
            [new JsonField('data', 'data'), ['foo' => 'bar'], JsonFieldSerializer::encodeJson(['foo' => 'bar'])],
            [new JsonField('data', 'data'), ['foo' => 1], JsonFieldSerializer::encodeJson(['foo' => 1])],
            [new JsonField('data', 'data'), ['foo' => 5.3], JsonFieldSerializer::encodeJson(['foo' => 5.3])],
            [new JsonField('data', 'data'), ['foo' => ['bar' => 'baz']], JsonFieldSerializer::encodeJson(['foo' => ['bar' => 'baz']])],

            [new JsonField('data', 'data'), null, null],
            [new JsonField('data', 'data', [], []), null, JsonFieldSerializer::encodeJson([])],

            [new JsonField('data', 'data', [], ['foo' => 'bar']), null, JsonFieldSerializer::encodeJson(['foo' => 'bar'])],
            [new JsonField('data', 'data', [], ['foo' => 1]), null, JsonFieldSerializer::encodeJson(['foo' => 1])],
            [new JsonField('data', 'data', [], ['foo' => 5.3]), null, JsonFieldSerializer::encodeJson(['foo' => 5.3])],
            [new JsonField('data', 'data', [], ['foo' => ['bar' => 'baz']]), null, JsonFieldSerializer::encodeJson(['foo' => ['bar' => 'baz']])],
        ];
    }

    /**
     * @dataProvider encodeProvider
     */
    public function testEncode(JsonField $field, $input, $expected): void
    {
        $kvPair = new KeyValuePair('password', $input, true);
        $actual = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertEquals($expected, $actual);
    }

    public function decodeProvider(): array
    {
        return [
            [new JsonField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 'bar']), ['foo' => 'bar']],

            [new JsonField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 1]), ['foo' => 1]],
            [new JsonField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 5.3]), ['foo' => 5.3]],
            [new JsonField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => ['bar' => 'baz']]), ['foo' => ['bar' => 'baz']]],

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
        $actual = $this->serializer->decode($field, $input);
        static::assertEquals($expected, $actual);
    }
}
