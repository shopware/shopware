<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Compatibility;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @decrecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, `defaultRunInterval` will be required in the future
 */
#[Package('core')]
class ScheduledTaskCompatibilitySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'addBackwardsCompatibility'];
    }

    public function addBackwardsCompatibility(PreWriteValidationEvent $event): void
    {
        if (Feature::isActive('v6.6.0.0')) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand) {
                continue;
            }

            if ($command->getEntityName() !== ScheduledTaskDefinition::ENTITY_NAME) {
                continue;
            }

            if ($command->hasField('default_run_interval')) {
                continue;
            }

            Feature::triggerDeprecationOrThrow(
                'v6.6.0.0',
                'ScheduledTaskDefinition::defaultRunInterval will be required in the future, please provide a value for this field.',
            );

            $command->addPayload('default_run_interval', $command->getPayload()['run_interval'] ?? 0);
        }
    }
}
