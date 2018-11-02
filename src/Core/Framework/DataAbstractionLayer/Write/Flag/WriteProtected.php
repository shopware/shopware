<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Flag;

class WriteProtected extends Flag
{
    /**
     * @var string
     */
    protected $permissionKey;

    public function __construct(string $permissionKey)
    {
        $this->permissionKey = $permissionKey;
    }

    public function getPermissionKey(): string
    {
        return $this->permissionKey;
    }
}
