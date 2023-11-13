<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Subscriber;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class PluginAclPrivilegesSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly KernelPluginCollection $plugins)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AclRoleEvents::ACL_ROLE_LOADED_EVENT => 'onAclRoleLoaded',
        ];
    }

    public function onAclRoleLoaded(EntityLoadedEvent $event): void
    {
        if (!$event->getDefinition() instanceof AclRoleDefinition) {
            return;
        }

        /** @var AclRoleEntity[] $aclRoles */
        $aclRoles = $event->getEntities();

        if (!$aclRoles) {
            return;
        }

        $additionalRolePrivileges = $this->getAdditionalRolePrivileges();

        foreach ($additionalRolePrivileges as $additionalRole => $additionalPrivileges) {
            foreach ($aclRoles as $aclRole) {
                if ($additionalRole === AclRoleDefinition::ALL_ROLE_KEY || \in_array($additionalRole, $aclRole->getPrivileges(), true)) {
                    $newPrivileges = array_values(array_unique(array_merge($aclRole->getPrivileges(), $additionalPrivileges)));
                    $aclRole->setPrivileges($newPrivileges);
                }
            }
        }
    }

    /**
     * returns a unique, merged array of all role privileges to be added by plugins
     *
     * @return array<string, list<string>>
     */
    private function getAdditionalRolePrivileges(): array
    {
        $rolePrivileges = [];

        foreach ($this->plugins->getActives() as $plugin) {
            $rolePrivileges = array_replace_recursive($rolePrivileges, $plugin->enrichPrivileges());
        }

        return $rolePrivileges;
    }
}
