<?php declare(strict_types=1);

namespace Salutation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\ConstraintViolationExceptionInterface;
use Shopware\Core\System\Salutation\DefaultSalutationValidator;
use Shopware\Core\System\Salutation\SalutationDefinition;

class DefaultSalutationValidatorTest extends TestCase
{
    /**
     * @dataProvider eventProvider
     * @doesNotPerformAssertions
     */
    public function testValidateShouldNotAllowDeletion(PreWriteValidationEvent $event): void
    {
        Feature::skipTestIfActive('FEATURE_NEXT_7739', $this);

        $validator = new DefaultSalutationValidator();

        // Assertions are done in the mocked methods
        $validator->validate($event);
    }

    public function eventProvider(): \Generator
    {
        yield 'order delete event' => [
            $this->getEvent(new OrderDefinition(), 'c5d8b53e80824ea28416d56d8d7c1e43', true),
        ];

        yield 'salutation delete event' => [
            $this->getEvent(new SalutationDefinition(), 'd1d7671d5d354af7a014661162928074', true),
        ];

        yield 'default salutation delete event' => [
            $this->getEvent(new SalutationDefinition(), Defaults::SALUTATION, false),
        ];
    }

    private function getEvent(EntityDefinition $entityDefinition, string $entityId, bool $isDeletable): PreWriteValidationEvent
    {
        $event = static::createMock(PreWriteValidationEvent::class);

        $event->method('getCommands')
            ->willReturn($this->getCommands($entityDefinition, $entityId));

        $event->method('getExceptions')
            ->willReturn($this->getExceptions($isDeletable));

        return $event;
    }

    /**
     * @return array<WriteCommand>
     */
    private function getCommands(EntityDefinition $entityDefinition, string $entityId): array
    {
        $command = static::createMock(DeleteCommand::class);

        $command->method('getDefinition')
            ->willReturn($entityDefinition);

        $command->method('getPrimaryKey')
            ->willReturn([
                'id' => Uuid::fromHexToBytes($entityId),
            ]);

        return [
            $command,
        ];
    }

    private function getExceptions(bool $isDeletable): WriteException
    {
        $exception = static::createMock(WriteException::class);

        if ($isDeletable) {
            $exception->expects(static::never())
                ->method('add');
        } else {
            $exception->expects(static::once())
                ->method('add')
                ->with(static::callback(static function (ConstraintViolationExceptionInterface $exception): bool {
                    return $exception->getViolations()->get(0)->getCode() === DefaultSalutationValidator::VIOLATION_CODE;
                }));
        }

        return $exception;
    }
}
