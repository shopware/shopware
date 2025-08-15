<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class CartBehavior extends Struct
{
    /**
     * @deprecated tag:v6.8.0 - $isRecalculation will be removed and is replaced by specific {@see CheckoutPermissions::*}
     *
     * @param array<string, bool> $permissions
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see $this->hasPermission(CheckoutPermissions::*)}
     */
    public function isRecalculation(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(
            self::class,
            __METHOD__,
            'v6.8.0.0',
            self::class . '::hasPermission(CheckoutPermissions::*)',
        ));

        return !Feature::isActive('v6.8.0.0') && $this->isRecalculation;
    }

    /**
     * @internal
     *
     * @template TReturn of mixed
     *
     * @param \Closure(): TReturn $closure
     *
     * @return TReturn
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
