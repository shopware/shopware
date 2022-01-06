<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the WishlistPage is loaded
 *
 * @hook-use-case data_loading
 */
class WishlistPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'wishlist-page-loaded';

    private WishlistPage $page;

    public function __construct(WishlistPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): WishlistPage
    {
        return $this->page;
    }
}
