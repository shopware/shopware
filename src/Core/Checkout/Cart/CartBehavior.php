<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartBehavior extends Struct
{
    /**
     * @deprecated tag:v6.3.0 Use fine granular context permissions instead
     *
     * @var bool
     */
    protected $isRecalculation = false;

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

    /**
     * @deprecated tag:v6.3.0 Use fine granular context permissions instead
     */
    public function isRecalculation(): bool
    {
        return $this->isRecalculation;
    }

    /**
     * @deprecated tag:v6.3.0 Use fine granular context permissions instead
     */
    public function setIsRecalculation(bool $isRecalculation): CartBehavior
    {
        $this->isRecalculation = $isRecalculation;

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'cart_behavior';
    }
}
