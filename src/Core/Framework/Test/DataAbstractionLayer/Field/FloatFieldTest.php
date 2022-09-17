<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @group FloatFieldTest
 * @internal
 */
class FloatFieldTest extends TestCase
{
    use KernelTestBehaviour;

    public function testFloatFieldSerializerNullValue(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        $this->expectException(WriteConstraintViolationException::class);

        try {
            $serializer->encode(
                $this->getFloatField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('/count', $e->getViolations()->get(0)->getPropertyPath());
            /* Unexpected language has to be fixed NEXT-9419 */
            //static::assertSame('This value should not be blank.', $e->getViolations()->get(0)->getMessage());

            throw $e;
        }
    }

    public function testFloatFieldSerializerWrongValueType(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', 'foo', false);

        $this->expectException(WriteConstraintViolationException::class);

        try {
            $serializer->encode(
                $this->getFloatField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('/count', $e->getViolations()->get(0)->getPropertyPath());
            /* Unexpected language has to be fixed NEXT-9419 */
            //static::assertSame('This value should be of type int.', $e->getViolations()->get(0)->getMessage());

            throw $e;
        }
    }

    public function testFloatFieldSerializerMaxLimitReached(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', 1000, false);

        $this->expectException(WriteConstraintViolationException::class);

        try {
            $serializer->encode(
                $this->getFloatField(true, 0, 5),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('/count', $e->getViolations()->get(0)->getPropertyPath());
            /* Unexpected language has to be fixed NEXT-9419 */
            //static::assertSame('This value should be of type int.', $e->getViolations()->get(0)->getMessage());

            throw $e;
        }
    }

    public function testFloatFieldSerializerMinLimitReached(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', -1000, false);

        $this->expectException(WriteConstraintViolationException::class);

        try {
            $serializer->encode(
                $this->getFloatField(true, 0, 5),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();
        } catch (WriteConstraintViolationException $e) {
            static::assertSame('/count', $e->getViolations()->get(0)->getPropertyPath());
            /* Unexpected language has to be fixed NEXT-9419 */
            //static::assertSame('This value should be of type int.', $e->getViolations()->get(0)->getMessage());

            throw $e;
        }
    }

    public function testFloatFieldSerializerZeroValue(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', 0.0, false);

        $field = $this->getFloatField();

        static::assertSame(
            0.0,
            $serializer->encode(
                $field,
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testFloatFieldSerializerFloatValue(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', 15.0, false);

        static::assertSame(
            15.0,
            $serializer->encode(
                $this->getFloatField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current()
        );
    }

    public function testFloatFieldSerializerNotRequiredValue(): void
    {
        $serializer = $this->getContainer()->get(FloatFieldSerializer::class);

        $data = new KeyValuePair('count', null, false);

        static::assertNull(
            $serializer->encode(
                $this->getFloatField(false),
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

    private function getFloatField(bool $required = true, int $minValue = 0, int $maxValue = 1000): FloatField
    {
        $field = new FloatField('count', 'count', $minValue, $maxValue);

        return $required ? $field->addFlags(new ApiAware(), new Required()) : $field;
    }
}
