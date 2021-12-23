<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the ProductQuickViewWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @internal (flag:FEATURE_NEXT_17441)
 */
class ProductQuickViewWidgetLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'product-quick-view-widget-loaded';

    private MinimalQuickViewPage $page;

    public function __construct(MinimalQuickViewPage $page, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}
