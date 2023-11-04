<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class CartBehavior extends Struct
{
    /**
     * @param array<mixed> $permissions
     */
    public function __construct(
        private readonly array $permissions = [],
        private bool $hookAware = true,
        private readonly bool $isRecalculation = false
    ) {
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

    public function isRecalculation(): bool
    {
        return $this->isRecalculation;
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
