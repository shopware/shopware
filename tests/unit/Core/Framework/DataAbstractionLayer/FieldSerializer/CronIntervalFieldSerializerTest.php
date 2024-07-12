<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Cron\CronExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CronIntervalFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\CronInterval;
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
#[CoversClass(CronIntervalFieldSerializer::class)]
class CronIntervalFieldSerializerTest extends TestCase
{
    private const COMPLEX_CRON = '12,4 */2 * 1-4 MON#3';

    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private ValidatorInterface&MockObject $validator;

    private CronIntervalFieldSerializer $intervalFieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->intervalFieldSerializer = new CronIntervalFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testEncodeMethodWithCorrectDataWillReturnCronIntervalString(): void
    {
        $data = new KeyValuePair('key', new CronExpression(self::COMPLEX_CRON), false);

        $cronExpression = $this->intervalFieldSerializer->encode(
            new CronIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();

        static::assertIsString($cronExpression);
        static::assertEquals(self::COMPLEX_CRON, $cronExpression);
    }

    public function testEncodeMethodWithIncorrectFieldParameterTypeWillThrowInvalidSerializerException(): void
    {
        $data = new KeyValuePair('key', null, false);

        static::expectException(DataAbstractionLayerException::class);

        $this->intervalFieldSerializer->encode(
            new ManyToOneAssociationField('test', 'test', 'test'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithNoDataWillReturnNull(): void
    {
        $data = new KeyValuePair('key', null, false);

        $interval = $this->intervalFieldSerializer->encode(
            new CronIntervalField('name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        static::assertNull($interval->current());

        $interval->next();

        static::assertNull($interval->getReturn());
    }

    public function testEncodeMethodWithIncorrectDataWillThrowException(): void
    {
        $data = new KeyValuePair('key', new CronIntervalField('name', 'name'), false);

        $this->validator
            ->expects(static::exactly(2))
            ->method('validate')
            ->with($data->getValue(), static::callback(static function ($constraint): bool {
                return $constraint instanceof NotNull || ($constraint instanceof Type && $constraint->type === CronExpression::class);
            }))
            ->willReturn(new ConstraintViolationList([new ConstraintViolation('error', 'error', [], '', 'key', 'value')]));

        static::expectException(WriteConstraintViolationException::class);

        $this->intervalFieldSerializer->encode(
            (new CronIntervalField('name', 'name'))->setFlags(new Required()),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithStringWillReturnCronIntervalString(): void
    {
        $data = new KeyValuePair('key', self::COMPLEX_CRON, false);

        $cronExpression = $this->intervalFieldSerializer->encode(
            new CronIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();

        static::assertIsString($cronExpression);
        static::assertEquals(self::COMPLEX_CRON, $cronExpression);
    }

    public function testEncodeMethodWithIncorrectStringWillThrowInvalidCronIntervalFormatException(): void
    {
        $data = new KeyValuePair('key', self::COMPLEX_CRON . '-invalid', false);

        static::expectException(DataAbstractionLayerException::class);

        $this->intervalFieldSerializer->encode(
            new CronIntervalField('fake', 'fake'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        )->current();
    }

    public function testEncodeMethodWithIntWillYieldNull(): void
    {
        $data = new KeyValuePair('key', 123567, false);

        $interval = $this->intervalFieldSerializer->encode(
            new CronIntervalField('name', 'name'),
            $this->createStub(EntityExistence::class),
            $data,
            $this->createMock(WriteParameterBag::class)
        );

        static::assertNull($interval->current());

        $interval->next();

        static::assertNull($interval->getReturn());
    }

    public function testDecodeMethodWithCorrectValueWillReturnCronInterval(): void
    {
        $cronInterval = $this->intervalFieldSerializer->decode(
            new CronIntervalField('name', 'name'),
            self::COMPLEX_CRON
        );

        static::assertEquals(new CronInterval(self::COMPLEX_CRON), $cronInterval);
    }

    public function testDecodeMethodWithNoValueWillReturnNull(): void
    {
        $cronInterval = $this->intervalFieldSerializer->decode(
            new CronIntervalField('name', 'name'),
            null
        );

        static::assertNull($cronInterval);
    }

    public function testDecodeMethodWithIncorrectValueObjectWillReturnInvalidDateIntervalFormatException(): void
    {
        static::expectException(DataAbstractionLayerException::class);

        $this->intervalFieldSerializer->decode(
            new CronIntervalField('name', 'name'),
            'this-is-not-valid'
        );
    }
}
