<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

class OffcanvasCartPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'offcanvas-cart-page-loaded';

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
