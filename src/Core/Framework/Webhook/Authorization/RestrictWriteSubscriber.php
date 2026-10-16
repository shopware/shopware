<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class RestrictWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WriteAuthorizer $authorizer,
        private readonly SubscriptionValidator $subscriptionValidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'restrictWrite',
        ];
    }

    public function restrictWrite(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $this->restrictModification($event);
        $this->restrictSubscription($event);
    }

    private function restrictModification(PreWriteValidationEvent $event): void
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

    private function restrictSubscription(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME);
        if ($commands === []) {
            return;
        }

        $context = $event->getContext();

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return;
        }

        $subscriptions = [];
        foreach ($commands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $eventName = $command->getPayload()['event_name'] ?? null;
            if (!\is_string($eventName)) {
                continue;
            }

            $subscriptions[$eventName] = $eventName;
        }

        $privileges = $source->isAdmin() ? null : array_values($source->getPermissions());
        $refusals = $this->subscriptionValidator->validate($subscriptions, $privileges, Subscriber::fromSource($source), $context);

        foreach ([...$refusals->notHookable, ...$refusals->notPermitted] as $eventName) {
            $event->getExceptions()->add(WebhookException::webhookEventNotPermitted($eventName));
        }

        foreach ($refusals->missingPrivileges as $eventName => $missing) {
            $event->getExceptions()->add(WebhookException::webhookEventPrivilegesMissing($eventName, $missing));
        }
    }
}
