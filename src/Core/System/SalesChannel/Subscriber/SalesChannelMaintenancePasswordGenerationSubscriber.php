<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelMaintenancePasswordGenerationSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [EntityWriteEvent::class => 'rotateGeneration'];
    }

    public function rotateGeneration(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(SalesChannelDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand || !$command->hasField('maintenance')) {
                continue;
            }

            $command->addPayload('maintenance_password_generation', bin2hex(random_bytes(16)));
        }
    }
}
