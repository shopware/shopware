<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Struct\AssignArrayTrait;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\Struct\Fixture\AssignTestCollection;
use Shopware\Tests\Unit\Core\Framework\Struct\Fixture\AssignTestStruct;

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
            'randomArrayProperty' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
        ];

        $struct = (new AssignTestStruct([]))->assign($data);

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

        $structString = (new AssignTestStruct([]))->assign(['stdClass' => $class]);

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
            'randomArrayProperty' => [['id' => 'some-uuid-1'], ['id' => 'some-uuid-2']],
        ];

        $struct = (new AssignTestStruct([]))->assignRecursive($data);

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
        $structString = (new AssignTestStruct([]))->assignRecursive(['mixedType' => 'string']);
        $structStdClass = (new AssignTestStruct([]))->assignRecursive(['mixedType' => new \stdClass()]);
        $structStruct = (new AssignTestStruct([]))->assignRecursive(['mixedType' => ['id' => 'some-uuid']]);

        static::assertSame('string', $structString->getMixedType());
        static::assertInstanceOf(\stdClass::class, $structStdClass->getMixedType());
        static::assertInstanceOf(AssignTestStruct::class, $structStruct->getMixedType());
        static::assertSame('some-uuid', $structStruct->getMixedType()->getId());
    }

    public function testAssignObject(): void
    {
        $class = new \stdClass();
        $class->test = 'value';

        $structString = (new AssignTestStruct([]))->assignRecursive(['stdClass' => $class]);

        static::assertInstanceOf(\stdClass::class, $structString->getStdClass());
        static::assertSame('value', $structString->getStdClass()->test);
    }

    public function testSetterCalled(): void
    {
        $structString = (new AssignTestStruct([]))->assignRecursive(['int' => 1]);

        // Setter will increase input by 1
        static::assertSame(2, $structString->getInt());
    }

    public function testAssignValueWithoutSetter(): void
    {
        $structString = (new AssignTestStruct([]))->assignRecursive(['bool' => true]);

        // Bool has no setter method defined
        static::assertTrue($structString->getBool());
    }

    public function testUnionType(): void
    {
        $structAssignByArray = (new AssignTestStruct([]))->assignRecursive(['doubleTypeCollection' => [['id' => Uuid::randomHex()]]]);
        $structAssign = (new AssignTestStruct([]))->assignRecursive(['doubleTypeCollection' => new AssignTestCollection()]);
        $structEntity = (new AssignTestStruct([]))->assignRecursive(['doubleTypeCollection' => new EntityCollection()]);
        $structNull = (new AssignTestStruct([]))->assignRecursive(['doubleTypeCollection' => null]);

        static::assertInstanceOf(AssignTestCollection::class, $structAssignByArray->getDoubleTypeCollection());
        static::assertInstanceOf(AssignTestCollection::class, $structAssign->getDoubleTypeCollection());
        static::assertInstanceOf(EntityCollection::class, $structEntity->getDoubleTypeCollection());
        static::assertNull($structNull->getDoubleTypeCollection());
    }

    public function testSetNoTypedValue(): void
    {
        $structScalar = (new AssignTestStruct([]))->assignRecursive(['noType' => 'some-value']);
        $structObject = (new AssignTestStruct([]))->assignRecursive(['noType' => new \stdClass()]);

        static::assertSame('some-value', $structScalar->getNoType());
        static::assertInstanceOf(\stdClass::class, $structObject->getNoType());
    }

    public function testAssignDifferentCollectionTypes(): void
    {
        $data = [
            ['id' => 'first-id'],
            (new AssignTestStruct([]))->assignRecursive(['id' => 'second-id']),
            new \stdClass(),
            null,
            [],
            'some-value',
        ];

        $collection = (new AssignTestStruct([]))->assignRecursive(['assignCollection' => $data])->getAssignCollection();

        static::assertNotNull($collection);
        static::assertCount(3, $collection);

        $array = $collection->get(0);
        $struct = $collection->get(1);
        $empty = $collection->get(2);

        static::assertInstanceOf(AssignTestStruct::class, $array);
        static::assertInstanceOf(AssignTestStruct::class, $struct);
        static::assertInstanceOf(AssignTestStruct::class, $empty);

        static::assertSame('first-id', $array->getId());
        static::assertSame('second-id', $struct->getId());
        static::assertArrayNotHasKey('id', $empty->getVars());
        static::assertArrayNotHasKey('_uniqueIdentifier', $empty->getVars());
    }

    public function testAssignWithWrongType(): void
    {
        $struct = (new AssignTestStruct([]))->assignRecursive(['int' => 'im-a-string']);

        static::assertNull($struct->getInt());
    }

    public function testIntersectionType(): void
    {
        $class = (new class extends Struct implements \JsonSerializable, \Countable {
            /**
             * @var array<mixed>
             */
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

        $structWithInstance = (new AssignTestStruct([]))->assignRecursive(['intersectionType' => new $class()]);
        $structWithArray = (new AssignTestStruct([]))->assignRecursive(['intersectionType' => ['property' => ['some string']]]);

        static::assertInstanceOf($class::class, $structWithInstance->getIntersectionType());
        static::assertNull($structWithArray->getIntersectionType());
    }

    public function testSetEmptyValue(): void
    {
        $struct = new AssignTestStruct([]);
        $struct->setArray(['some' => 'value']);
        $struct->setString('some string');

        static::assertSame(['some' => 'value'], $struct->getArray());
        static::assertSame('some string', $struct->getString());

        $updatedStruct = $struct->assignRecursive(['array' => [], 'string' => null]);

        static::assertEmpty($updatedStruct->getArray());
        static::assertNull($updatedStruct->getString());
    }

    public function testSetEmptyObject(): void
    {
        $struct = (new AssignTestStruct([]))->assignRecursive(['assignTestStruct' => []]);

        static::assertInstanceOf(AssignTestStruct::class, $struct->getAssignTestStruct());
        static::assertEmpty(array_filter($struct->getAssignTestStruct()->getVars()));
    }

    public function testRandomArrayProperty(): void
    {
        $values = (new AssignTestStruct([]))->assignRecursive([
            'randomStringProperty' => 'some-value',
            'randomArrayProperty' => ['some' => 'value'],
        ])->getVars();

        static::assertArrayHasKey('randomStringProperty', $values);
        static::assertArrayHasKey('randomArrayProperty', $values);

        static::assertSame('some-value', $values['randomStringProperty']);
        static::assertSame(['some' => 'value'], $values['randomArrayProperty']);
    }

    public function testInconsistentNullableSetter(): void
    {
        $struct = new AssignTestStruct([]);
        $struct->setStdClass(new \stdClass());

        static::assertInstanceOf(\stdClass::class, $struct->getStdClass());

        $struct->assignRecursive(['stdClass' => null]);

        static::assertNull($struct->getStdClass());
    }

    public function testAssignWithDifferentType(): void
    {
        $struct = (new AssignTestStruct([]))->assignRecursive(['stdClass' => new AssignTestStruct([])]);

        static::assertNull($struct->getStdClass());
    }

    public function testAssignNotNullableProperty(): void
    {
        $struct = (new AssignTestStruct([]))->assignRecursive(['notNullableString' => 'some-string']);

        static::assertSame('some-string', $struct->getNotNullableString());

        $struct->assignRecursive(['notNullableString' => null]);

        static::assertSame('some-string', $struct->getNotNullableString());
    }
}
