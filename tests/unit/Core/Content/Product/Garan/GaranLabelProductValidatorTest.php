<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelProductValidator;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelProductValidator::class)]
class GaranLabelProductValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private GaranLabelProductValidator $validator;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->validator = new GaranLabelProductValidator();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = GaranLabelProductValidator::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertSame('validate', $events[PreWriteValidationEvent::class]);
    }

    public function testIgnoresNonProductEntities(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('tax'), ['id' => $id, 'guarantee_months' => 1], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresPayloadWithoutGuaranteeMonthsKey(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresNullGuaranteeMonths(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => null], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestWith([36])]
    #[TestWith([48])]
    #[TestWith([30])]
    public function testAllowsValidGuaranteeMonths(int $months): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => $months], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testCatchesInsertWithTooLowDuration(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => 24], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelProductValidator::VIOLATION_CODE, $violation->getCode());
        static::assertSame('/insert/guaranteeMonths', $violation->getPropertyPath());
    }

    public function testCatchesInsertWithNonMultipleOfSix(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => 37], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelProductValidator::VIOLATION_CODE, $violation->getCode());
    }

    public function testCatchesNonIntegerValue(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => '36'], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelProductValidator::VIOLATION_CODE, $violation->getCode());
    }

    public function testCatchesUpdateWithInvalidDuration(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand($this->registry->getByEntityName('product'), ['id' => $id, 'guarantee_months' => 12], ['id' => $id], static::createStub(EntityExistence::class), '/update'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame('/update/guaranteeMonths', $violation->getPropertyPath());
    }
}
