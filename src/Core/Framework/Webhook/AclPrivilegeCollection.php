<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class AclPrivilegeCollection
{
    /**
     * @param array<string> $privileges
     */
    public function __construct(private readonly array $privileges)
    {
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return \in_array($resource . ':' . $privilege, $this->privileges, true);
    }
}
