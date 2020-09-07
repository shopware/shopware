<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\ProductLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class MinimalQuickViewPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductLoader
     */
    private $productLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, ProductLoader $productLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->productLoader = $productLoader;
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): MinimalQuickViewPage
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId', '/productId');
        }

        $product = $this->productLoader->load($productId, $salesChannelContext, MinimalQuickViewPageCriteriaEvent::class);

        $page = new MinimalQuickViewPage($product);

        $event = new MinimalQuickViewPageLoadedEvent($page, $salesChannelContext, $request);

        $this->eventDispatcher->dispatch($event);

        return $page;
    }
}
