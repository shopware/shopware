<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class IntFieldTest extends TestCase
{
    use KernelTestBehaviour;

    public function testIntFieldSerializerNullValue()
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        self::expectException(InvalidFieldException::class);
        try {
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (InvalidFieldException $e) {
            self::assertSame('count', $e->getViolations()->get(0)->getPropertyPath());
            self::assertSame('This value should not be blank.', $e->getViolations()->get(0)->getMessage());
            throw $e;
        }
    }

    public function testIntFieldSerializerWrongValueType()
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 'foo', false);

        self::expectException(InvalidFieldException::class);
        try {
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (InvalidFieldException $e) {
            self::assertSame('count', $e->getViolations()->get(0)->getPropertyPath());
            self::assertSame('This value should be of type int.', $e->getViolations()->get(0)->getMessage());
            throw $e;
        }
    }

    public function testIntFieldSerializerZeroValue()
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 0, false);

        $field = $this->getIntField();

        self::assertSame(
            0,
            $serializer->encode(
                $field,
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testIntFieldSerializerIntValue()
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 15, false);

        self::assertSame(
            15,
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testIntFieldSerializerNotRequiredValue()
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        self::assertNull(
            $serializer->encode(
                $this->getIntField(false),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    /**
     * @return WriteParameterBag|MockObject
     */
    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    private function getEntityExisting(): EntityExistence
    {
        return new EntityExistence('foo', [], true, false, false, []);
    }

    private function getIntField($required = true): IntField
    {
        $field = new IntField('count', 'count');

        return $required ? $field->setFlags(new Required()) : $field;
    }
}
