<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Permission;

class AclPermission
{
    /**
     * @var string
     */
    private $resource;

    /**
     * @var string
     */
    private $privilege;

    public function __construct(string $resource, string $privilege)
    {
        $this->resource = $resource;
        $this->privilege = $privilege;
    }

    public function getPrivilege(): string
    {
        return $this->privilege;
    }

    public function getResource(): string
    {
        return $this->resource;
    }
}
