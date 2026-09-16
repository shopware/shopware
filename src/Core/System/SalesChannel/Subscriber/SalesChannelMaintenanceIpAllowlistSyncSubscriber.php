<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mirrors the new `maintenance_ip_allowlist` column and the deprecated `maintenance_ip_whitelist`
 * column on every sales channel write, so writes through either field stay in sync without DB triggers.
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed together with the deprecated `maintenance_ip_whitelist` column
 */
#[Package('discovery')]
class SalesChannelMaintenanceIpAllowlistSyncSubscriber implements EventSubscriberInterface
{
    private const ALLOWLIST_COLUMN = 'maintenance_ip_allowlist';
    private const WHITELIST_COLUMN = 'maintenance_ip_whitelist';

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'mirrorMaintenanceIpColumns',
        ];
    }

    public function mirrorMaintenanceIpColumns(EntityWriteEvent $event): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            return;
        }

        foreach ($event->getCommandsForEntity(SalesChannelDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            $this->mirror($command);
        }
    }

    private function mirror(WriteCommand $command): void
    {
        $hasAllowlist = $command->hasField(self::ALLOWLIST_COLUMN);
        $hasWhitelist = $command->hasField(self::WHITELIST_COLUMN);

        if (!$hasAllowlist && !$hasWhitelist) {
            return;
        }

        $payload = $command->getPayload();

        // the new column wins if both are part of the same write
        if ($hasAllowlist) {
            $command->addPayload(self::WHITELIST_COLUMN, $payload[self::ALLOWLIST_COLUMN]);

            return;
        }

        $command->addPayload(self::ALLOWLIST_COLUMN, $payload[self::WHITELIST_COLUMN]);
    }
}
