<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateIntervalFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DateIntervalFieldSerializer::class)]
class DateIntervalFieldSerializerTest extends TestCase
{
    private const REGEX_DATE_INTERVAL_VALIDATION = '/^P(\d+Y)(\d+M)(\d+D)T(\d+H)(\d+M)(\d+S)$/';

    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ValidatorInterface&MockObject $validator;

    private DateIntervalFieldSerializer $dateIntervalFieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->dateIntervalFieldSerializer = new DateIntervalFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testEncodeMethodWithCorrectDataWillReturnDateIntervalString(): void
    {
        $data = new KeyValuePair('key', new \DateInterval('P2Y5D'), false);

        $iterator = $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        $dateIntervalString = $iterator->current();

        \iterator_to_array($iterator);

        static::assertIsString($dateIntervalString);
        static::assertMatchesRegularExpression(self::REGEX_DATE_INTERVAL_VALIDATION, $dateIntervalString);
        static::assertEquals('P2Y0M5DT0H0M0S', $dateIntervalString);
    }

    public function testEncodeMethodWithIncorrectFieldParameterTypeWillThrowInvalidSerializerException(): void
    {
        $data = new KeyValuePair('key', null, false);

        static::expectException(DataAbstractionLayerException::class);

        $this->dateIntervalFieldSerializer->encode(
            new ManyToOneAssociationField('name', 'name', 'name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithNoDataWillReturnNull(): void
    {
        $data = new KeyValuePair('key', null, false);

        $intervalString = $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();

        static::assertNull($intervalString);
    }

    public function testEncodeMethodWithIncorrectDataTypeObjectWillThrowInvalidIntervalFieldDataTypeException(): void
    {
        $data = new KeyValuePair('key', new DateIntervalField('name', 'name'), false);

        $this->validator
            ->expects(static::exactly(2))
            ->method('validate')
            ->with($data->getValue(), static::callback(static function ($constraint): bool {
                return $constraint instanceof NotNull || ($constraint instanceof Type && $constraint->type === \DateInterval::class);
            }))
            ->willReturn(new ConstraintViolationList([new ConstraintViolation('error', 'error', [], '', 'key', 'value')]));

        static::expectException(WriteConstraintViolationException::class);

        $this->dateIntervalFieldSerializer->encode(
            (new DateIntervalField('name', 'name'))->setFlags(new Required()),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithStringWillReturnDateIntervalString(): void
    {
        $data = new KeyValuePair('key', 'P2Y5DT2S', false);

        $dateIntervalString = $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();

        static::assertIsString($dateIntervalString);
        static::assertMatchesRegularExpression(self::REGEX_DATE_INTERVAL_VALIDATION, $dateIntervalString);
        static::assertEquals('P2Y0M5DT0H0M2S', $dateIntervalString);
    }

    public function testEncodeMethodWithIncorrectStringWillThrowInvalidDateIntervalFormatException(): void
    {
        $data = new KeyValuePair('key', 'P2Y5DT2S-Invalid', false);

        static::expectException(DataAbstractionLayerException::class);
        $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithIntWillYieldNull(): void
    {
        $data = new KeyValuePair('key', 123567, false);

        $interval = $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        static::assertNull($interval->current());

        $interval->next();

        static::assertNull($interval->getReturn());
    }

    public function testEncodeMethodWithNullWillYieldNull(): void
    {
        $data = new KeyValuePair('key', null, false);

        $interval = $this->dateIntervalFieldSerializer->encode(
            new DateIntervalField('name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        static::assertNull($interval->current());

        $interval->next();

        static::assertNull($interval->getReturn());
    }

    public function testDecodeMethodWithCorrectValueWillReturnDateInterval(): void
    {
        $dateInterval = $this->dateIntervalFieldSerializer->decode(
            new DateIntervalField('name', 'name'),
            'P2Y0M5D'
        );

        static::assertEquals(new DateInterval('P2Y0M5D'), $dateInterval);
    }

    public function testDecodeMethodWithNoValueWillReturnNull(): void
    {
        $dateInterval = $this->dateIntervalFieldSerializer->decode(
            new DateIntervalField('name', 'name'),
            null
        );

        static::assertNull($dateInterval);
    }

    public function testDecodeMethodWithIncorrectValueObjectWillReturnInvalidDateIntervalFormatException(): void
    {
        static::expectException(DataAbstractionLayerException::class);

        $this->dateIntervalFieldSerializer->decode(
            new DateIntervalField('name', 'name'),
            'this-is-not-valid'
        );
    }
}
