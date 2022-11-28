<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package checkout
 */
class CartBehavior extends Struct
{
    /**
     * @var array<mixed>
     */
    private array $permissions = [];

    private bool $hookAware;

    /**
     * @param array<mixed> $permissions
     */
    public function __construct(array $permissions = [], bool $hookAware = true)
    {
        $this->permissions = $permissions;
        $this->hookAware = $hookAware;
    }

    public function hasPermission(string $permission): bool
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
