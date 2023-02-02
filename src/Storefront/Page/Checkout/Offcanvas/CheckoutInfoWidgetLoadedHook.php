<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CheckoutInfoWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class CheckoutInfoWidgetLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'checkout-info-widget-loaded';

    private OffcanvasCartPage $page;

    public function __construct(OffcanvasCartPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): OffcanvasCartPage
    {
        return $this->page;
    }
}
