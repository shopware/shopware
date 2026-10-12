<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Delivers an event to an app webhook only when the privileges of the app's role allow it.
 * The privileges of a role are loaded on first use and kept until reset.
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
        if ($webhook->appId === null) {
            return true;
        }

        return $event->isAllowed($webhook->appId, $this->getRolePrivileges($webhook->appAclRoleId));
    }

    public function reset(): void
    {
        $this->rolePrivileges = [];
    }

    private function getRolePrivileges(?string $roleId): AclPrivilegeCollection
    {
        if ($roleId === null) {
            return new AclPrivilegeCollection([]);
        }

        return $this->rolePrivileges[$roleId] ??= $this->webhookLoader->getPrivilegesForRoles([$roleId])[$roleId] ?? new AclPrivilegeCollection([]);
    }
}
