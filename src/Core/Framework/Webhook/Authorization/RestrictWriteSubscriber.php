<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\WriteAuthorizer;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
class RestrictWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WriteAuthorizer $authorizer,
        private readonly SubscriptionValidator $subscriptionValidator,
        private readonly WebhookLoader $webhookLoader,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'restrictModification',
            PostWriteValidationEvent::class => 'restrictSubscription',
        ];
    }

    public function restrictModification(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

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

    public function restrictSubscription(PostWriteValidationEvent $event): void
    {
        $writes = self::findSubscriptionWrites($event);
        if ($writes === []) {
            return;
        }

        $context = $event->getContext();

        $source = $context->getSource();
        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource) {
            return;
        }

        $webhooks = $this->webhookLoader->getWebhooksByIds(array_keys($writes));
        $rolePrivileges = $this->webhookLoader->getPrivilegesForRoles(array_values(array_unique(array_merge([], ...array_map(
            static fn (Webhook $webhook): array => $webhook->ownerRoleIds,
            $webhooks
        )))));

        $subscriber = Subscriber::fromSource($source);

        foreach ($webhooks as $webhook) {
            $command = $writes[$webhook->id];
            $aclRoleIdsNotHeld = array_values(array_unique(array_diff($webhook->aclRoleIds ?? [], $webhook->ownerRoleIds)));
            if ($aclRoleIdsNotHeld !== [] && \array_key_exists('acl_role_ids', $command->getPayload())) {
                $event->getExceptions()->add(self::aclRolesNotHeld($command, $aclRoleIdsNotHeld));

                continue;
            }

            $refusals = $this->subscriptionValidator->validate(
                [$webhook->eventName => $webhook->eventName],
                self::resolvePrivileges($webhook, $rolePrivileges),
                $subscriber,
                $context
            );

            foreach ([...$refusals->notHookable, ...$refusals->notPermitted] as $eventName) {
                $event->getExceptions()->add(WebhookException::webhookEventNotPermitted($eventName));
            }

            foreach ($refusals->missingPrivileges as $eventName => $missing) {
                $event->getExceptions()->add(WebhookException::webhookEventPrivilegesMissing($eventName, $missing));
            }
        }
    }

    /**
     * @return array<string, WriteCommand> keyed by webhook id
     */
    private static function findSubscriptionWrites(PostWriteValidationEvent $event): array
    {
        $writes = [];
        foreach ($event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (\array_key_exists('event_name', $payload) || \array_key_exists('acl_role_ids', $payload)) {
                $writes[Uuid::fromBytesToHex($command->getPrimaryKey()['id'])] = $command;
            }
        }

        return $writes;
    }

    /**
     * @param list<string> $roleIds
     */
    private static function aclRolesNotHeld(WriteCommand $command, array $roleIds): WriteConstraintViolationException
    {
        return new WriteConstraintViolationException(
            new ConstraintViolationList(array_map(
                static fn (string $roleId): ConstraintViolation => new ConstraintViolation(
                    \sprintf('Role "%s" is not held by the webhook\'s owner.', $roleId),
                    'Role "{{ roleId }}" is not held by the webhook\'s owner.',
                    ['{{ roleId }}' => $roleId],
                    null,
                    '/aclRoleIds',
                    $roleId,
                ),
                $roleIds
            )),
            $command->getPath()
        );
    }

    /**
     * @param array<string, AclPrivilegeCollection> $rolePrivileges
     *
     * @return list<string>|null
     */
    private static function resolvePrivileges(Webhook $webhook, array $rolePrivileges): ?array
    {
        if ($webhook->ownerType === OwnerType::Admin) {
            return null;
        }

        $privileges = [];
        foreach ($webhook->ownerRoleIds as $roleId) {
            $privileges = [...$privileges, ...($rolePrivileges[$roleId] ?? new AclPrivilegeCollection([]))->getPrivileges()];
        }

        return array_values(array_unique($privileges));
    }
}
