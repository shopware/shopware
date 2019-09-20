<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class IntFieldTest extends TestCase
{
    use KernelTestBehaviour;

    public function testIntFieldSerializerNullValue(): void
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('count', $e->getViolations()->get(0)->getPropertyPath());
            static::assertSame('This value should not be blank.', $e->getViolations()->get(0)->getMessage());
            throw $e;
        }
    }

    public function testIntFieldSerializerWrongValueType(): void
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 'foo', false);

        $this->expectException(WriteConstraintViolationException::class);
        try {
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('count', $e->getViolations()->get(0)->getPropertyPath());
            static::assertSame('This value should be of type int.', $e->getViolations()->get(0)->getMessage());
            throw $e;
        }
    }

    public function testIntFieldSerializerZeroValue(): void
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 0, false);

        $field = $this->getIntField();

        static::assertSame(
            0,
            $serializer->encode(
                $field,
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testIntFieldSerializerIntValue(): void
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', 15, false);

        static::assertSame(
            15,
            $serializer->encode(
                $this->getIntField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testIntFieldSerializerNotRequiredValue(): void
    {
        $serializer = $this->getContainer()->get(IntFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        static::assertNull(
            $serializer->encode(
                $this->getIntField(false),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    private function getEntityExisting(): EntityExistence
    {
        return new EntityExistence(null, [], true, false, false, []);
    }

    private function getIntField($required = true): IntField
    {
        $field = new IntField('count', 'count');

        return $required ? $field->addFlags(new Required()) : $field;
    }
}
