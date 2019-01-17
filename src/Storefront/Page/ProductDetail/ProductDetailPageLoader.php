<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductDetailPageLoader
{
    /**
     * @var ProductDetailPageletLoader
     */
    private $productDetailPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ProductDetailPageletLoader $productDetailPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productDetailPageletLoader = $productDetailPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): ProductDetailPageStruct
    {
        $page = new ProductDetailPageStruct();
        $page->setProductDetail(
            $this->productDetailPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            ProductDetailPageLoadedEvent::NAME,
            new ProductDetailPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
