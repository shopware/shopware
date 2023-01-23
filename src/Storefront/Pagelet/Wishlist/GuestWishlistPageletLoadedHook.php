<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the GuestWishlistPagelet is loaded
 *
 * @package storefront
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class GuestWishlistPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'guest-wishlist-pagelet-loaded';

    public function __construct(private readonly GuestWishlistPagelet $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): GuestWishlistPagelet
    {
        return $this->page;
    }
}
