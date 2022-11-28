<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package checkout
 */
class CartBehavior extends Struct
{
    /**
     * @var array
     */
    private $permissions = [];

    private bool $hookAware;

    public function __construct(array $permissions = [], bool $hookAware = true)
    {
        $this->permissions = $permissions;
        $this->hookAware = $hookAware;
    }

    /**
     * @deprecated tag:v6.5.0 - Return type will change to bool
     *
     * @phpstan-ignore-next-line when return type will be added we can remove the ignore
     */
    public function hasPermission(string $permission)
    {
        return !empty($this->permissions[$permission]);
    }

    public function getApiAlias(): string
    {
        return 'cart_behavior';
    }

    public function hookAware(): bool
    {
        return $this->hookAware;
    }

    /**
     * @internal
     *
     * @return mixed
     */
    public function disableHooks(\Closure $closure)
    {
        $before = $this->hookAware;

        $this->hookAware = false;

        $result = $closure();

        $this->hookAware = $before;

        return $result;
    }
}
