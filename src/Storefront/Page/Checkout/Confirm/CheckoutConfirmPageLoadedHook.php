<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CheckoutConfirmPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class CheckoutConfirmPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'checkout-confirm-page-loaded';

    private CheckoutConfirmPage $page;

    public function __construct(CheckoutConfirmPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CheckoutConfirmPage
    {
        return $this->page;
    }
}
