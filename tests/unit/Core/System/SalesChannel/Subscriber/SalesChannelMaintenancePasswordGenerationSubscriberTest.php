<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelMaintenancePasswordGenerationSubscriber;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelMaintenancePasswordGenerationSubscriber::class)]
class SalesChannelMaintenancePasswordGenerationSubscriberTest extends TestCase
{
    public function testRotatesGenerationWhenMaintenanceIsWritten(): void
    {
        $command = $this->createMock(UpdateCommand::class);
        $command->method('getEntityName')->willReturn(SalesChannelDefinition::ENTITY_NAME);
        $command->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'maintenance');
        $command->expects($this->once())->method('addPayload')->with(
            'maintenance_password_generation',
            static::callback(static fn (mixed $value): bool => \is_string($value) && \strlen($value) === 32)
        );

        $this->dispatch($command);
    }

    public function testDoesNotChangeGenerationForOtherSalesChannelWrites(): void
    {
        $command = $this->createMock(UpdateCommand::class);
        $command->method('getEntityName')->willReturn(SalesChannelDefinition::ENTITY_NAME);
        $command->method('hasField')->willReturn(false);
        $command->expects($this->never())->method('addPayload');

        $this->dispatch($command);
    }

    public function testSkipsDeleteCommands(): void
    {
        $command = $this->createMock(DeleteCommand::class);
        $command->method('getEntityName')->willReturn(SalesChannelDefinition::ENTITY_NAME);
        $command->expects($this->never())->method('hasField');
        $command->expects($this->never())->method('addPayload');

        $this->dispatch($command);
    }

    private function dispatch(WriteCommand&MockObject $command): void
    {
        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command]
        );

        (new SalesChannelMaintenancePasswordGenerationSubscriber())->rotateGeneration($event);
    }
}
