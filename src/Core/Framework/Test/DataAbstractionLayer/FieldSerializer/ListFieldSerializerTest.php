<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ListFieldSerializerTest extends TestCase
{
    /**
     * @dataProvider decodeStrictProvider
     */
    public function testStrictDecode(ListField $field, $input, $expected): void
    {
        $serializer = new ListFieldSerializer(
            $this->createMock(ValidatorInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class)
        );
        /* @deprecated tag:v6.5.0 Remove $field->setStrict(true); */
        $field->setStrict(true);

        $actual = $serializer->decode($field, $input);
        static::assertEquals($expected, $actual);
    }

    /**
     * @deprecated tag:v6.5.0 Remove test
     * @dataProvider decodeProvider
     */
    public function testDecode(ListField $field, $input, $expected): void
    {
        $serializer = new ListFieldSerializer(
            $this->createMock(ValidatorInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class)
        );
        $actual = $serializer->decode($field, $input);
        static::assertEquals($expected, $actual);
    }

    public function decodeStrictProvider(): array
    {
        return [
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 'bar']), ['bar']],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson([0 => 'bar', 1 => 'foo']), ['bar', 'foo']],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 1]), [1]],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 5.3]), [5.3]],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => ['bar' => 'baz']]), [['bar' => 'baz']]],
            [new ListField('data', 'data'), null, null],
        ];
    }

    /**
     * @deprecated tag:v6.5.0 Remove provider
     */
    public function decodeProvider(): array
    {
        return [
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 'bar']), ['foo' => 'bar']],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson([0 => 'bar', 1 => 'foo']), [0 => 'bar', 1 => 'foo']],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 1]), ['foo' => 1]],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => 5.3]), ['foo' => 5.3]],
            [new ListField('data', 'data'), JsonFieldSerializer::encodeJson(['foo' => ['bar' => 'baz']]), ['foo' => ['bar' => 'baz']]],
            [new ListField('data', 'data'), null, null],
        ];
    }
}
