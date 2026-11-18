<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
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
    private const COLUMNS = ['event_name', 'acl_role_ids', 'app_id', 'owner_integration_id', 'owner_user_id'];

    public function __construct(
        private readonly SubscriptionValidator $subscriptionValidator,
        private readonly AclRoleLoader $aclRoleLoader,
        private readonly WriteOutcome $writeOutcome,
    ) {
    }

    /**
     * Every change to an existing webhook must be allowed, and each webhook is then judged as it will be after the
     * write. A webhook rejected by one check is not checked further.
     *
     * @param list<WriteCommand> $commands the webhook writes, in the order they are run
     *
     * @return list<HttpException>
     */
    public function getViolations(array $commands, Context $context): array
    {
        if ($commands === [] || $context->getScope() === Context::SYSTEM_SCOPE) {
            return [];
        }

        $rows = [];
        foreach ($this->writeOutcome->touchedRows($commands, self::COLUMNS) as $row) {
            $rows[Uuid::fromBytesToHex($row->primaryKey['id'])] = $row;
        }

        $violations = $this->getModificationViolations(array_filter($rows, $this->changesStoredWebhook(...)), $context);

        $written = array_filter($rows, static fn (OutcomeRow $row): bool => $row->payloadContainsAny('event_name', 'acl_role_ids'));
        $violations += $this->getAclRoleIdsViolations(array_diff_key($written, $violations));
        $violations += $this->getSubscriptionViolations(array_diff_key($written, $violations), $context);

        return array_values($violations);
    }

    /**
     * Only the owner, or an admin, may change or delete an app-less webhook, and nobody may change an app's webhook.
     *
     * @param array<string, OutcomeRow> $rows the stored webhooks the write changes or deletes, keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getModificationViolations(array $rows, Context $context): array
    {
        $source = $context->getSource();
        $violations = [];

        foreach ($rows as $webhookId => $row) {
            $subscription = WebhookSubscription::fromRow($row->toStoredArray());
            if ($subscription?->belongsToApp()) {
                // an admin may still switch an app's webhook on or off, e.g. after it was disabled for failing
                if (!$row->payloadContainsOnly('active', 'updated_at') || !$source instanceof AdminApiSource || !$source->isAdmin()) {
                    $violations[$webhookId] = WebhookException::appWebhookNotModifiable($webhookId);
                }

                continue;
            }

            // a webhook without an app or owner has no subscription, so only an admin may change it
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
     * @param array<string, OutcomeRow> $rows keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getAclRoleIdsViolations(array $rows): array
    {
        $violations = [];
        foreach ($rows as $webhookId => $row) {
            $aclRoleIds = $row->getPayloadValue('acl_role_ids');
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
                $row->path
            );
        }

        return $violations;
    }

    /**
     * For writes through the Admin API, the listed aclRoleIds must exist and be held by the owner, and the subscribed
     * event must be covered by the webhook's roles. An update is judged on the stored webhook with the update applied.
     *
     * @param array<string, OutcomeRow> $rows keyed by webhook id
     *
     * @return array<string, HttpException> keyed by webhook id
     */
    private function getSubscriptionViolations(array $rows, Context $context): array
    {
        $caller = ApiCaller::of($context->getSource());
        if ($caller === null) {
            return [];
        }

        $written = array_filter(array_map(static fn (OutcomeRow $row): ?WebhookSubscription => WebhookSubscription::fromRow($row->toResultingArray()), $rows));
        if ($written === []) {
            return [];
        }

        [$webhookRoles, $violations] = $this->resolveRoles($rows, $written);
        $rolePrivileges = $this->aclRoleLoader->getPrivilegesForRoles(array_merge(...array_column($webhookRoles, 'roleIds')));

        $subscriptions = [];
        $privileges = [];
        foreach ($webhookRoles as $webhookId => $roles) {
            $subscriptions[$webhookId] = $written[$webhookId]->eventName;
            $privileges[$webhookId] = $this->resolvePrivileges($roles, $rolePrivileges);
        }

        $refusals = $this->subscriptionValidator->validate($subscriptions, $privileges, Subscriber::api($caller), $context);

        foreach ([...$refusals->notHookable, ...$refusals->notPermitted] as $webhookId) {
            $violations[$webhookId] = WebhookException::webhookEventNotPermitted($subscriptions[$webhookId]);
        }

        foreach ($refusals->missingPrivileges as $webhookId => $missing) {
            $violations[$webhookId] = WebhookException::webhookEventPrivilegesMissing($subscriptions[$webhookId], $missing);
        }

        return $violations;
    }

    /**
     * The roles each webhook will act with: its owner's roles, limited to its aclRoleIds when set. A webhook whose
     * written aclRoleIds lists a role that does not exist or that its owner does not hold is rejected.
     *
     * @param array<string, OutcomeRow> $rows keyed by webhook id
     * @param array<string, WebhookSubscription> $written keyed by webhook id
     *
     * @return array{array<string, AclRoles>, array<string, HttpException>} the roles and the violations, both keyed by webhook id
     */
    private function resolveRoles(array $rows, array $written): array
    {
        $ownerRoles = $this->loadOwnerRoles($written);
        $listedRoleIds = array_merge(...array_map(
            static fn (WebhookSubscription $webhook): array => $webhook->aclRoleIds ?? [],
            array_values($written)
        ));
        $existingRoleIds = array_keys($this->aclRoleLoader->getPrivilegesForRoles($listedRoleIds));

        $webhookRoles = [];
        $violations = [];
        foreach ($written as $webhookId => $webhook) {
            $roles = $ownerRoles[$webhookId] ?? null;
            if ($roles === null) {
                continue;
            }

            if ($webhook->aclRoleIds !== null) {
                $roles = $roles->intersect($webhook->aclRoleIds);
            }

            $unknown = array_values(array_unique(array_diff($webhook->aclRoleIds ?? [], $existingRoleIds)));
            $notHeld = array_values(array_unique(array_diff($webhook->aclRoleIds ?? [], $roles->roleIds, $unknown)));
            if (($unknown !== [] || $notHeld !== []) && $rows[$webhookId]->payloadContainsAny('acl_role_ids')) {
                $violations[$webhookId] = $this->aclRolesRejected($rows[$webhookId], $unknown, $notHeld);

                continue;
            }

            $webhookRoles[$webhookId] = $roles;
        }

        return [$webhookRoles, $violations];
    }

    /**
     * @param array<string, WebhookSubscription> $written keyed by webhook id
     *
     * @return array<string, AclRoles> each webhook's owner roles, keyed by webhook id
     */
    private function loadOwnerRoles(array $written): array
    {
        $userRoles = $this->aclRoleLoader->getUserRoles($this->ownerIdsOf($written, SubscriberType::User));
        $integrationRoles = $this->aclRoleLoader->getIntegrationRoles($this->ownerIdsOf($written, SubscriberType::Integration));

        $roles = [];
        foreach ($written as $webhookId => $webhook) {
            $roles[$webhookId] = $webhook->ownerType === SubscriberType::User
                ? $userRoles[$webhook->ownerId] ?? null
                : $integrationRoles[$webhook->ownerId] ?? null;
        }

        return array_filter($roles);
    }

    /**
     * @param array<string, WebhookSubscription> $written keyed by webhook id
     *
     * @return list<string>
     */
    private function ownerIdsOf(array $written, SubscriberType $type): array
    {
        return array_column(
            array_filter($written, static fn (WebhookSubscription $webhook): bool => $webhook->ownerType === $type),
            'ownerId'
        );
    }

    /**
     * @param list<string> $unknown
     * @param list<string> $notHeld
     */
    private function aclRolesRejected(OutcomeRow $row, array $unknown, array $notHeld): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        foreach ($unknown as $roleId) {
            $violations->add($this->aclRoleViolation('Role "{{ roleId }}" does not exist.', $roleId));
        }

        foreach ($notHeld as $roleId) {
            $violations->add($this->aclRoleViolation('Role "{{ roleId }}" is not held by the webhook\'s owner.', $roleId));
        }

        return new WriteConstraintViolationException($violations, $row->path);
    }

    private function aclRoleViolation(string $template, string $roleId): ConstraintViolation
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

    /**
     * A webhook that exists before the write, so the write changes or deletes it.
     */
    private function changesStoredWebhook(OutcomeRow $row): bool
    {
        return !$row->isNew || $row->isDeleted;
    }

    /**
     * @param array<string, AclPrivilegeCollection> $rolePrivileges
     *
     * @return list<string>|null null for unrestricted roles, which may subscribe to everything
     */
    private function resolvePrivileges(AclRoles $roles, array $rolePrivileges): ?array
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
