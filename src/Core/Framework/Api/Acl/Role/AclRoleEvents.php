<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AclRoleEvents
{
    final public const ACL_ROLE_WRITTEN_EVENT = 'acl_role.written';

    final public const ACL_ROLE_DELETED_EVENT = 'acl_role.deleted';

    final public const ACL_ROLE_LOADED_EVENT = 'acl_role.loaded';

    final public const ACL_ROLE_SEARCH_RESULT_LOADED_EVENT = 'acl_role.search.result.loaded';
}
