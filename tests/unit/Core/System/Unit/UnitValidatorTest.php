<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\System\Unit\UnitIsoCodes;
use Shopware\Core\System\Unit\UnitValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(UnitValidator::class)]
class UnitValidatorTest extends TestCase
{
    private UnitDefinition $unitDefinition;

    private CurrencyDefinition $currencyDefinition;

    private UnitValidator $validator;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [UnitDefinition::class, CurrencyDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $unitDefinition = $registry->get(UnitDefinition::class);
        static::assertInstanceOf(UnitDefinition::class, $unitDefinition);
        $this->unitDefinition = $unitDefinition;

        $currencyDefinition = $registry->get(CurrencyDefinition::class);
        static::assertInstanceOf(CurrencyDefinition::class, $currencyDefinition);
        $this->currencyDefinition = $currencyDefinition;

        $this->validator = new UnitValidator(new UnitIsoCodes());
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'preValidate'],
            UnitValidator::getSubscribedEvents()
        );
    }

    public function testAcceptsListedCode(): void
    {
        $event = $this->buildEvent([$this->buildInsertCommand(['iso_code' => 'KGM'])]);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testAcceptsMissingOrEmptyCode(): void
    {
        $event = $this->buildEvent([
            $this->buildInsertCommand([]),
            $this->buildInsertCommand(['iso_code' => null]),
        ]);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsUnlistedCode(): void
    {
        $event = $this->buildEvent([$this->buildInsertCommand(['iso_code' => 'kgm'])]);

        $this->validator->preValidate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);

        $violation = $exceptions[0]->getViolations()->get(0);
        static::assertSame('/isoCode', $violation->getPropertyPath());
        static::assertSame(UnitValidator::VIOLATION_INVALID_ISO_CODE, $violation->getCode());
        static::assertSame('kgm', $violation->getInvalidValue());
    }

    public function testValidatesUpdateCommands(): void
    {
        $command = new UpdateCommand(
            $this->unitDefinition,
            ['iso_code' => 'ZZZ'],
            ['id' => Uuid::randomBytes()],
            $this->existence($this->unitDefinition),
            '/0'
        );
        $event = $this->buildEvent([$command]);

        $this->validator->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresDeleteCommands(): void
    {
        $event = $this->buildEvent([new DeleteCommand(
            $this->unitDefinition,
            ['id' => Uuid::randomBytes()],
            $this->existence($this->unitDefinition)
        )]);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresCommandsForOtherEntities(): void
    {
        $event = $this->buildEvent([new InsertCommand(
            $this->currencyDefinition,
            ['iso_code' => 'not a unit code'],
            ['id' => Uuid::randomBytes()],
            $this->existence($this->currencyDefinition),
            '/0'
        )]);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildInsertCommand(array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->unitDefinition,
            $payload,
            ['id' => Uuid::randomBytes()],
            $this->existence($this->unitDefinition),
            '/0'
        );
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function buildEvent(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands
        );
    }

    private function existence(EntityDefinition $definition): EntityExistence
    {
        return EntityExistence::createForEntity($definition->getEntityName(), ['id' => Uuid::randomHex()]);
    }
}
