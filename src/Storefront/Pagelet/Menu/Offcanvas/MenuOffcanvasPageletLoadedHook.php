<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the MenuOffcanvasPagelet is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class MenuOffcanvasPageletLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'menu-offcanvas-pagelet-loaded';

    private MenuOffcanvasPagelet $page;

    public function __construct(MenuOffcanvasPagelet $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): MenuOffcanvasPagelet
    {
        return $this->page;
    }
}
