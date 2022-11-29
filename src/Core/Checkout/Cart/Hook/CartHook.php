<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Hook;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @package checkout
 */

/**
 * Triggered during the cart calculation process.
 *
 * @hook-use-case cart_manipulation
 *
 * @since 6.4.8.0
 */
class CartHook extends Hook implements CartAware
{
    public const HOOK_NAME = 'cart';

    private Cart $cart;

    private SalesChannelContext $salesChannelContext;

    /**
     * @internal
     */
    public function __construct(Cart $cart, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->cart = $cart;
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
