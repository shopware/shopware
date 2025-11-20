<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AssignArrayTrait::class)]
class AssignArrayTraitTest extends TestCase
{
    public function testSerializedAssign(): void
    {
        $data = [
            'id' => 'some-uuid',
            'int' => 1,
            'float' => 1.2,
            'string' => 'some-string',
            'bool' => true,
            'array' => ['key' => 'value'],
            'stdClass' => ['property' => 'value'],
            'struct' => ['property' => 'value'],
            'assignTestStruct' => ['string' => 'value'],
            'mixedType' => ['string' => 'other-value'],
            'collection' => [['firstElementProperty' => 'value'], ['secondElementProperty' => 'value']],
            'assignCollection' => [['string' => 'Hello World'], ['float' => 123.456]],
            'doubleTypeCollection' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
            'randomProperty' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
        ];

        $struct = (new AssignTestStruct())->assign($data);

        static::assertSame($data['id'], $struct->getId());
        static::assertSame($data['id'], $struct->getVars()['_uniqueIdentifier']);
        static::assertSame($data['int'], $struct->getInt()); // "1", because setter method is not called
        static::assertSame($data['float'], $struct->getFloat());
        static::assertSame($data['string'], $struct->getString());
        static::assertSame($data['bool'], $struct->getBool());
        static::assertSame($data['array'], $struct->getArray());
        static::assertNull($struct->getStdClass());
        static::assertNull($struct->getStruct());
        static::assertNull($struct->getAssignTestStruct());
        static::assertNull($struct->getMixedType());
        static::assertNull($struct->getCollection());
        static::assertNull($struct->getAssignCollection());
        static::assertNull($struct->getDoubleTypeCollection());
    }

    public function testAssignObjectNotRecursive(): void
    {
        $class = new \stdClass();
        $class->test = 'value';

        $structString = (new AssignTestStruct())->assign(['stdClass' => $class]);

        static::assertInstanceOf(\stdClass::class, $structString->getStdClass());
        static::assertSame('value', $structString->getStdClass()->test);
    }

    public function testSerializedAssignRecursive(): void
    {
        $data = [
            'id' => 'some-uuid',
            'int' => 1,
            'float' => 1.2,
            'string' => 'some-string',
            'bool' => true,
            'array' => ['key' => 'value'],
            'stdClass' => ['property' => 'value'],
            'struct' => ['property' => 'value'],
            'assignTestStruct' => ['string' => 'value'],
            'mixedType' => ['string' => 'other-value'],
            'collection' => [['firstElementProperty' => 'value'], ['secondElementProperty' => 'value']],
            'assignCollection' => [['string' => 'Hello World'], ['float' => 123.456]],
            'doubleTypeCollection' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
            'randomProperty' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
        ];

        $struct = (new AssignTestStruct())->assign($data, true);

        static::assertSame($data['id'], $struct->getId());
        static::assertSame($data['id'], $struct->getVars()['_uniqueIdentifier']);
        static::assertSame($data['int'] + 1, $struct->getInt()); // "+ 1", because setter method *is* called
        static::assertSame($data['float'], $struct->getFloat());
        static::assertSame($data['string'], $struct->getString());
        static::assertSame($data['bool'], $struct->getBool());
        static::assertSame($data['array'], $struct->getArray());
        static::assertNull($struct->getStdClass());
        static::assertNull($struct->getStruct());
        static::assertInstanceOf(AssignTestStruct::class, $struct->getAssignTestStruct());
        static::assertSame('value', $struct->getAssignTestStruct()->getString());
        static::assertInstanceOf(AssignTestStruct::class, $struct->getMixedType());
        static::assertSame('other-value', $struct->getMixedType()->getString());
        static::assertNull($struct->getCollection());
        static::assertInstanceOf(AssignTestCollection::class, $struct->getAssignCollection());
        static::assertCount(2, $struct->getAssignCollection());
        static::assertSame('Hello World', $struct->getAssignCollection()->first()?->getString());
        static::assertSame(123.456, $struct->getAssignCollection()->last()?->getFloat());
        static::assertInstanceOf(AssignTestCollection::class, $struct->getDoubleTypeCollection());
        static::assertCount(2, $struct->getDoubleTypeCollection());
        static::assertSame('some-uuid-1', $struct->getDoubleTypeCollection()->first()?->getId());
        static::assertSame('some-uuid-2', $struct->getDoubleTypeCollection()->last()?->getId());
    }

    public function testMixedType(): void
    {
        $structString = (new AssignTestStruct())->assign(['mixedType' => 'string'], true);
        $structStdClass = (new AssignTestStruct())->assign(['mixedType' => new \stdClass()], true);
        $structStruct = (new AssignTestStruct())->assign(['mixedType' => ['id' => 'some-uuid']], true);

        static::assertSame('string', $structString->getMixedType());
        static::assertInstanceOf(\stdClass::class, $structStdClass->getMixedType());
        static::assertInstanceOf(AssignTestStruct::class, $structStruct->getMixedType());
        static::assertSame('some-uuid', $structStruct->getMixedType()->getId());
    }

    public function testAssignObject(): void
    {
        $class = new \stdClass();
        $class->test = 'value';

        $structString = (new AssignTestStruct())->assign(['stdClass' => $class], true);

        static::assertInstanceOf(\stdClass::class, $structString->getStdClass());
        static::assertSame('value', $structString->getStdClass()->test);
    }

    public function testSetterCalled(): void
    {
        $structString = (new AssignTestStruct())->assign(['int' => 1], true);

        // Setter will increase input by 1
        static::assertSame(2, $structString->getInt());
    }

    public function testAssignValueWithoutSetter(): void
    {
        $structString = (new AssignTestStruct())->assign(['bool' => true], true);

        // Bool has no setter method defined
        static::assertTrue($structString->getBool());
    }

    public function testUnionType(): void
    {
        $structAssignByArray = (new AssignTestStruct())->assign(['doubleTypeCollection' => [['id' => Uuid::randomHex()]]], true);
        $structAssign = (new AssignTestStruct())->assign(['doubleTypeCollection' => new AssignTestCollection()], true);
        $structEntity = (new AssignTestStruct())->assign(['doubleTypeCollection' => new EntityCollection()], true);
        $structNull = (new AssignTestStruct())->assign(['doubleTypeCollection' => null], true);

        static::assertInstanceOf(AssignTestCollection::class, $structAssignByArray->getDoubleTypeCollection());
        static::assertInstanceOf(AssignTestCollection::class, $structAssign->getDoubleTypeCollection());
        static::assertInstanceOf(EntityCollection::class, $structEntity->getDoubleTypeCollection());
        static::assertNull($structNull->getDoubleTypeCollection());
    }

    public function testSetNoTypedValue(): void
    {
        $structScalar = (new AssignTestStruct())->assign(['noType' => 'some-value'], true);
        $structObject = (new AssignTestStruct())->assign(['noType' => new \stdClass()], true);

        static::assertSame('some-value', $structScalar->getNoType());
        static::assertInstanceOf(\stdClass::class, $structObject->getNoType());
    }

    public function testAssignDifferentCollectionTypes(): void
    {
        $data = [
            ['id' => 'first-id'],
            (new AssignTestStruct())->assign(['id' => 'second-id'], true),
            new \stdClass(),
            null,
            [],
            'some-value',
        ];

        $struct = (new AssignTestStruct())->assign(['assignCollection' => $data], true);
        $collection = $struct->getAssignCollection();

        static::assertNotNull($collection);
        static::assertCount(2, $collection);

        $first = $collection->first();
        $second = $collection->last();

        static::assertInstanceOf(AssignTestStruct::class, $first);
        static::assertInstanceOf(AssignTestStruct::class, $second);
        static::assertSame('first-id', $first->getId());
        static::assertSame('second-id', $second->getId());
    }

    public function testAssignWithWrongType(): void
    {
        $struct = (new AssignTestStruct())->assign(['int' => 'im-a-string'], true);

        static::assertNull($struct->getInt());
    }

    public function testIntersectionType(): void
    {
        $class = (new class extends Struct implements \JsonSerializable, \Countable {
            protected array $property;

            public function count(): int
            {
                return \count($this->property);
            }

            public function jsonSerialize(): array
            {
                return $this->property;
            }
        });

        $structWithInstance = (new AssignTestStruct())->assign(['intersectionType' => new $class()], true);
        $structWithArray = (new AssignTestStruct())->assign(['intersectionType' => ['property' => ['some string']]], true);

        static::assertInstanceOf($class::class, $structWithInstance->getIntersectionType());
        static::assertNull($structWithArray->getIntersectionType());
    }

    public function testSetEmptyValue(): void
    {
        $struct = new AssignTestStruct();
        $struct->setArray(['some' => 'value']);
        $struct->setString('some string');

        static::assertSame(['some' => 'value'], $struct->getArray());
        static::assertSame('some string', $struct->getString());

        $struct->assign(['array' => [], 'string' => null], true);

        static::assertEmpty($struct->getArray());
        static::assertNull($struct->getString());
    }

    public function testInconsistentNullableSetter(): void
    {
        $struct = new AssignTestStruct();
        $struct->setStdClass(new \stdClass());

        static::assertInstanceOf(\stdClass::class, $struct->getStdClass());

        $struct->assign(['stdClass' => null], true);

        static::assertNull($struct->getStdClass());
    }

    public function testAssignWithDifferentType(): void
    {
        $struct = new AssignTestStruct();
        $struct->assign(['stdClass' => new AssignTestStruct()], true);

        static::assertNull($struct->getStdClass());
    }

    public function testAssignNotNullableProperty(): void
    {
        $struct = new AssignTestStruct();
        $struct->assign(['notNullableString' => 'some-string'], true);

        static::assertSame('some-string', $struct->getNotNullableString());

        $struct->assign(['notNullableString' => null], true);

        static::assertSame('some-string', $struct->getNotNullableString());
    }
}
