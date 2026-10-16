<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Delivers an event to a webhook only when the privileges of its owner's roles allow it. An admin
 * owner receives every event. The privileges of a role are loaded on first use and kept until reset.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class PrivilegePolicy implements Policy, EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, AclPrivilegeCollection>
     */
    private array $rolePrivileges = [];

    public function __construct(private readonly WebhookLoader $webhookLoader)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'acl_role.written' => 'reset',
            AppPermissionsUpdated::class => 'reset',
        ];
    }

    public function handles(string $eventName): bool
    {
        return true;
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return true;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        return match ($webhook->ownerType) {
            OwnerType::Admin => true,
            OwnerType::Restricted => $event->isAllowed($webhook->appId ?? Hookable::NO_APP_ID, $this->getOwnerPrivileges($webhook->ownerRoleIds)),
        };
    }

    public function reset(): void
    {
        $this->rolePrivileges = [];
    }

    /**
     * @param list<string> $roleIds
     */
    private function getOwnerPrivileges(array $roleIds): AclPrivilegeCollection
    {
        $this->cacheRolePrivileges($roleIds);

        $privileges = array_merge(...array_map(
            fn (string $roleId): array => $this->rolePrivileges[$roleId]->getPrivileges(),
            $roleIds,
        ));

        return new AclPrivilegeCollection(array_values(array_unique($privileges)));
    }

    /**
     * @param list<string> $roleIds
     */
    private function cacheRolePrivileges(array $roleIds): void
    {
        $uncachedRoleIds = array_values(array_diff($roleIds, array_keys($this->rolePrivileges)));
        if ($uncachedRoleIds === []) {
            return;
        }

        $privileges = $this->webhookLoader->getPrivilegesForRoles($uncachedRoleIds);
        foreach ($uncachedRoleIds as $roleId) {
            $this->rolePrivileges[$roleId] = $privileges[$roleId] ?? new AclPrivilegeCollection([]);
        }
    }
}
