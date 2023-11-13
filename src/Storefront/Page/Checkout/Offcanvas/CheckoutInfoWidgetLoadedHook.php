<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CheckoutInfoWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('storefront')]
class CheckoutInfoWidgetLoadedHook extends PageLoadedHook implements CartAware
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'checkout-info-widget-loaded';

    public function __construct(
        private readonly OffcanvasCartPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        if ($this->getCart()->getSource()) {
            return self::HOOK_NAME . '-' . $this->getCart()->getSource();
        }

        return self::HOOK_NAME;
    }

    public function getPage(): OffcanvasCartPage
    {
        return $this->page;
    }

    public function getCart(): Cart
    {
        return $this->page->getCart();
    }
}
