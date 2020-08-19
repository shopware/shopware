<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

class AclPrivilegeCollection
{
    /**
     * @var array<string>
     */
    private $privileges;

    /**
     * @param array<string> $privileges
     */
    public function __construct(array $privileges)
    {
        $this->privileges = $privileges;
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return in_array($resource . ':' . $privilege, $this->privileges, true);
    }
}
