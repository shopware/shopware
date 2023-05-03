<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Hook;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHookFactory;
use Shopware\Core\Checkout\Cart\Facade\PriceFactoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered during the cart calculation process.
 *
 * @hook-use-case cart_manipulation
 *
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('checkout')]
class CartHook extends Hook implements CartAware
{
    final public const HOOK_NAME = 'cart';

    private readonly SalesChannelContext $salesChannelContext;

    /**
     * @internal
     */
    public function __construct(
        private readonly Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public static function getServiceIds(): array
    {
        return [
            CartFacadeHookFactory::class,
            PriceFactoryFactory::class,
            SystemConfigFacadeHookFactory::class,
        ];
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
