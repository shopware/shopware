<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
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
            PreWriteValidationEvent::class => 'restrictWrite',
        ];
    }

    public function restrictWrite(PreWriteValidationEvent $event): void
    {
        $ids = array_column(
            $event->findPrimaryKeys(
                WebhookDefinition::ENTITY_NAME,
                static fn (WriteCommand $command): bool => ($command instanceof UpdateCommand || $command instanceof DeleteCommand)
                    && !$command instanceof CascadeDeleteCommand
            ),
            'id'
        );

        if ($ids === []) {
            return;
        }

        $violations = $this->authorizer->getModificationViolations(
            array_values(Uuid::fromBytesToHexList($ids)),
            $event->getContext()
        );

        foreach ($violations as $violation) {
            $event->getExceptions()->add($violation);
        }
    }
}
