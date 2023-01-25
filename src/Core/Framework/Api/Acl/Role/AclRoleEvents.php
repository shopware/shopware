<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AclRoleEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const ACL_ROLE_WRITTEN_EVENT = 'acl_role.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const ACL_ROLE_DELETED_EVENT = 'acl_role.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const ACL_ROLE_LOADED_EVENT = 'acl_role.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const ACL_ROLE_SEARCH_RESULT_LOADED_EVENT = 'acl_role.search.result.loaded';
}
