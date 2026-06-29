<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelMaintenanceIpAllowlistSyncSubscriber;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelMaintenanceIpAllowlistSyncSubscriber::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class SalesChannelMaintenanceIpAllowlistSyncSubscriberTest extends TestCase
{
    public function testSubscribesToEntityWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'mirrorMaintenanceIpColumns'],
            SalesChannelMaintenanceIpAllowlistSyncSubscriber::getSubscribedEvents()
        );
    }

    public function testMirrorsNewAllowlistToDeprecatedWhitelist(): void
    {
        $value = json_encode(['127.0.0.1'], \JSON_THROW_ON_ERROR);

        $command = $this->createCommand(InsertCommand::class, ['maintenance_ip_allowlist' => $value]);
        $command->expects($this->once())->method('addPayload')->with('maintenance_ip_whitelist', $value);

        $this->dispatch($command);
    }

    public function testMirrorsDeprecatedWhitelistToNewAllowlist(): void
    {
        $value = json_encode(['10.0.0.1'], \JSON_THROW_ON_ERROR);

        $command = $this->createCommand(UpdateCommand::class, ['maintenance_ip_whitelist' => $value]);
        $command->expects($this->once())->method('addPayload')->with('maintenance_ip_allowlist', $value);

        $this->dispatch($command);
    }

    public function testNewColumnWinsWhenBothArePartOfTheSameWrite(): void
    {
        $allowlist = json_encode(['127.0.0.1'], \JSON_THROW_ON_ERROR);
        $whitelist = json_encode(['10.0.0.1'], \JSON_THROW_ON_ERROR);

        $command = $this->createCommand(UpdateCommand::class, [
            'maintenance_ip_allowlist' => $allowlist,
            'maintenance_ip_whitelist' => $whitelist,
        ]);
        $command->expects($this->once())->method('addPayload')->with('maintenance_ip_whitelist', $allowlist);

        $this->dispatch($command);
    }

    public function testSkipsWritesWithoutMaintenanceIpColumns(): void
    {
        $command = $this->createCommand(UpdateCommand::class, ['name' => 'Storefront']);
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

    public function testIsNoOpWhenMajorIsActive(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $command = $this->createCommand(InsertCommand::class, [
                'maintenance_ip_allowlist' => json_encode(['127.0.0.1'], \JSON_THROW_ON_ERROR),
            ]);
            $command->expects($this->never())->method('addPayload');

            $this->dispatch($command);
        });
    }

    /**
     * @param class-string<WriteCommand> $class
     * @param array<string, mixed> $payload
     *
     * @return WriteCommand&MockObject
     */
    private function createCommand(string $class, array $payload): WriteCommand
    {
        $command = $this->createMock($class);
        $command->method('getEntityName')->willReturn(SalesChannelDefinition::ENTITY_NAME);
        $command->method('getPayload')->willReturn($payload);
        $command->method('hasField')->willReturnCallback(
            static fn (string $storageName): bool => \array_key_exists($storageName, $payload)
        );

        return $command;
    }

    private function dispatch(WriteCommand $command): void
    {
        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command]
        );

        (new SalesChannelMaintenanceIpAllowlistSyncSubscriber())->mirrorMaintenanceIpColumns($event);
    }
}
