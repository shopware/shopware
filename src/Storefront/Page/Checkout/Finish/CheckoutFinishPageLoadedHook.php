<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the CheckoutFinishPage is loaded
 *
 * @hook-use-case data_loading
 */
class CheckoutFinishPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'checkout-finish-page-loaded';

    private CheckoutFinishPage $page;

    public function __construct(CheckoutFinishPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): CheckoutFinishPage
    {
        return $this->page;
    }
}
