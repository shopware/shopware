<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartBehavior extends Struct
{
    /**
     * @var array
     */
    private $permissions = [];

    public function __construct(array $permissions = [])
    {
        $this->permissions = $permissions;
    }

    public function hasPermission(string $permission)
    {
        return !empty($this->permissions[$permission]);
    }

    public function getApiAlias(): string
    {
        return 'cart_behavior';
    }
}
