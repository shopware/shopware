<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class RestrictWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly WriteAuthorizer $authorizer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $commands = [];
        foreach ($event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof CascadeDeleteCommand) {
                $commands[Uuid::fromBytesToHex($command->getPrimaryKey()['id'])] = $command;
            }
        }

        foreach ($this->authorizer->getViolations($commands, $event->getContext()) as $violation) {
            $event->getExceptions()->add($violation);
        }
    }
}
