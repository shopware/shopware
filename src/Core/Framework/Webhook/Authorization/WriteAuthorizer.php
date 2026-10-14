<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Decides whether a write to webhooks is allowed: who may change a webhook, and which roles and events it may use.
 *
 * @internal
 */
#[Package('framework')]
class WriteAuthorizer
{
    public function __construct(
        private readonly SubscriptionValidator $subscriptionValidator,
        private readonly WebhookLoader $webhookLoader,
        private readonly AclRoleLoader $aclRoleLoader,
    ) {
    }

    /**
     * The checks run in order, and a webhook rejected by one is not checked further.
     *
     * @param array<string, WriteCommand> $commands the webhook writes, keyed by webhook id
     *
     * @return list<HttpException>
     */
    public function getViolations(array $commands, Context $context): array
    {
        if ($commands === [] || $context->getScope() === Context::SYSTEM_SCOPE) {
            return [];
        }

        // the stored webhooks that are changed or deleted, used by the first and the last check
        $stored = $this->webhookLoader->getSubscriptions(array_keys(array_filter($commands, self::isModification(...))));

        $violations = $this->getModificationViolations($commands, $stored, $context);
        $violations += $this->getAclRoleIdsViolations(array_diff_key($commands, $violations));
        $violations += $this->getSubscriptionViolations(array_diff_key($commands, $violations), $stored, $context);

        return array_values($violations);
    }

    /**
     * Only the owner, or an admin, may change or delete an app-less webhook, and nobody may change an app's webhook.
     *
     * @param array<string, WriteCommand> $commands keyed by webhook id
     * @param array<string, WebhookSubscription> $stored keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getModificationViolations(array $commands, array $stored, Context $context): array
    {
        $source = $context->getSource();
        $violations = [];

        foreach (array_keys(array_filter($commands, self::isModification(...))) as $webhookId) {
            $subscription = $stored[$webhookId] ?? null;
            if ($subscription?->belongsToApp()) {
                $violations[$webhookId] = WebhookException::appWebhookNotModifiable($webhookId);

                continue;
            }

            // a webhook without an app or owner is not loaded, so only an admin may change it
            if (!$source instanceof AdminApiSource || $source->isAdmin() || $subscription?->isOwnedBy($source)) {
                continue;
            }

            $violations[$webhookId] = WebhookException::webhookNotOwned($webhookId);
        }

        return $violations;
    }

    /**
     * aclRoleIds is either left out, to use all of the owner's roles, or a list of role ids.
     *
     * @param array<string, WriteCommand> $commands keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getAclRoleIdsViolations(array $commands): array
    {
        $violations = [];
        foreach ($commands as $webhookId => $command) {
            $aclRoleIds = $command->getPayload()['acl_role_ids'] ?? null;
            if (!\is_string($aclRoleIds)) {
                continue;
            }

            $aclRoleIds = Json::decodeToList($aclRoleIds);
            if ($aclRoleIds === []) {
                $message = 'aclRoleIds must not be empty. Leave it out to use all of the owner\'s roles.';
            } elseif (array_filter($aclRoleIds) !== $aclRoleIds) {
                $message = 'aclRoleIds must not contain empty entries.';
            } else {
                continue;
            }

            $violations[$webhookId] = new WriteConstraintViolationException(
                new ConstraintViolationList([new ConstraintViolation($message, $message, [], null, '/aclRoleIds', $aclRoleIds)]),
                $command->getPath()
            );
        }

        return $violations;
    }

    /**
     * For writes through the Admin API, the listed aclRoleIds must exist and be held by the owner, and the subscribed
     * event must be covered by the webhook's roles. An update is judged on the stored webhook with the update applied.
     *
     * @param array<string, WriteCommand> $commands keyed by webhook id
     * @param array<string, WebhookSubscription> $stored keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getSubscriptionViolations(array $commands, array $stored, Context $context): array
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return [];
        }

        $commands = array_filter(
            $commands,
            static fn (WriteCommand $command): bool => ($command instanceof InsertCommand || $command instanceof UpdateCommand)
                && $command->hasAnyField('event_name', 'acl_role_ids')
        );

        if ($commands === []) {
            return [];
        }

        $written = $this->toSubscriptions($commands, $stored, $source);
        [$webhookRoles, $violations] = $this->resolveRoles($commands, $written);
        $rolePrivileges = $this->aclRoleLoader->getPrivilegesForRoles(array_merge(...array_column($webhookRoles, 'roleIds')));

        $subscriptions = [];
        $privileges = [];
        foreach ($webhookRoles as $webhookId => $roles) {
            $subscriptions[$webhookId] = $written[$webhookId]->eventName;
            $privileges[$webhookId] = self::resolvePrivileges($roles, $rolePrivileges);
        }

        $refusals = $this->subscriptionValidator->validate($subscriptions, $privileges, Subscriber::api(ApiCallerType::of($source)), $context);

        foreach ([...$refusals->notHookable, ...$refusals->notPermitted] as $webhookId) {
            $violations[$webhookId] = WebhookException::webhookEventNotPermitted($subscriptions[$webhookId]);
        }

        foreach ($refusals->missingPrivileges as $webhookId => $missing) {
            $violations[$webhookId] = WebhookException::webhookEventPrivilegesMissing($subscriptions[$webhookId], $missing);
        }

        return $violations;
    }

    /**
     * The webhooks as they will be after the write. An API insert is always owned by the caller, see RecordOwnerSubscriber.
     * Changes to an app's webhook never get here, getModificationViolations() already rejected them.
     *
     * @param array<string, WriteCommand> $commands keyed by webhook id
     * @param array<string, WebhookSubscription> $stored keyed by webhook id
     *
     * @return array<string, WebhookSubscription> keyed by webhook id
     */
    private function toSubscriptions(array $commands, array $stored, AdminApiSource $source): array
    {
        $written = [];
        foreach ($commands as $webhookId => $command) {
            $current = $stored[$webhookId] ?? null;
            $ownerType = $current?->ownerType;
            $ownerId = $current?->ownerId;
            if ($command instanceof InsertCommand) {
                $caller = ApiCallerType::of($source);
                $ownerType = SubscriberType::of($caller);
                $ownerId = $caller->idOf($source);
            }

            $payload = $command->getPayload();
            $eventName = $payload['event_name'] ?? $current?->eventName;
            if ($ownerType === null || $ownerId === null || !\is_string($eventName)) {
                continue;
            }

            $aclRoleIds = $current?->aclRoleIds;
            if ($command->hasField('acl_role_ids')) {
                $aclRoleIds = $payload['acl_role_ids'] !== null ? Json::decodeToList($payload['acl_role_ids']) : null;
            }

            $written[$webhookId] = new WebhookSubscription($webhookId, $ownerType, $ownerId, $eventName, $aclRoleIds);
        }

        return $written;
    }

    /**
     * The roles each webhook will act with: its owner's roles, limited to its aclRoleIds when set. A webhook whose
     * written aclRoleIds lists a role that does not exist or that its owner does not hold is rejected.
     *
     * @param array<string, WriteCommand> $commands keyed by webhook id
     * @param array<string, WebhookSubscription> $written keyed by webhook id
     *
     * @return array{array<string, AclRoles>, array<string, HttpException>} the roles and the violations, both keyed by webhook id
     */
    private function resolveRoles(array $commands, array $written): array
    {
        $ownerRoles = $this->aclRoleLoader->getRoles(array_column($written, 'ownerId'));
        $listedRoleIds = array_merge(...array_map(
            static fn (WebhookSubscription $webhook): array => $webhook->aclRoleIds ?? [],
            array_values($written)
        ));
        $existingRoleIds = $listedRoleIds !== [] ? array_keys($this->aclRoleLoader->getPrivilegesForRoles($listedRoleIds)) : [];

        $webhookRoles = [];
        $violations = [];
        foreach ($written as $webhookId => $webhook) {
            $roles = $ownerRoles[$webhook->ownerId] ?? null;
            if ($roles === null) {
                continue;
            }

            if ($webhook->aclRoleIds !== null) {
                $roles = $roles->intersect($webhook->aclRoleIds);
            }

            $unknown = array_values(array_unique(array_diff($webhook->aclRoleIds ?? [], $existingRoleIds)));
            $notHeld = array_values(array_unique(array_diff($webhook->aclRoleIds ?? [], $roles->roleIds, $unknown)));
            if (($unknown !== [] || $notHeld !== []) && $commands[$webhookId]->hasField('acl_role_ids')) {
                $violations[$webhookId] = self::aclRolesRejected($commands[$webhookId], $unknown, $notHeld);

                continue;
            }

            $webhookRoles[$webhookId] = $roles;
        }

        return [$webhookRoles, $violations];
    }

    /**
     * @param list<string> $unknown
     * @param list<string> $notHeld
     */
    private static function aclRolesRejected(WriteCommand $command, array $unknown, array $notHeld): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        foreach ($unknown as $roleId) {
            $violations->add(self::aclRoleViolation('Role "{{ roleId }}" does not exist.', $roleId));
        }

        foreach ($notHeld as $roleId) {
            $violations->add(self::aclRoleViolation('Role "{{ roleId }}" is not held by the webhook\'s owner.', $roleId));
        }

        return new WriteConstraintViolationException($violations, $command->getPath());
    }

    private static function aclRoleViolation(string $template, string $roleId): ConstraintViolation
    {
        return new ConstraintViolation(
            str_replace('{{ roleId }}', $roleId, $template),
            $template,
            ['{{ roleId }}' => $roleId],
            null,
            '/aclRoleIds',
            $roleId,
        );
    }

    private static function isModification(WriteCommand $command): bool
    {
        return $command instanceof UpdateCommand || $command instanceof DeleteCommand;
    }

    /**
     * @param array<string, AclPrivilegeCollection> $rolePrivileges
     *
     * @return list<string>|null null for unrestricted roles, which may subscribe to everything
     */
    private static function resolvePrivileges(AclRoles $roles, array $rolePrivileges): ?array
    {
        if ($roles->unrestricted) {
            return null;
        }

        $privileges = [];
        foreach ($roles->roleIds as $roleId) {
            $privileges = [...$privileges, ...($rolePrivileges[$roleId] ?? new AclPrivilegeCollection([]))->getPrivileges()];
        }

        return array_values(array_unique($privileges));
    }
}
