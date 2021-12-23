<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CheckoutCartPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @internal (flag:FEATURE_NEXT_17441)
 */
class CheckoutCartPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'checkout-cart-page-loaded';

    private CheckoutCartPage $page;

    public function __construct(CheckoutCartPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CheckoutCartPage
    {
        return $this->page;
    }
}
