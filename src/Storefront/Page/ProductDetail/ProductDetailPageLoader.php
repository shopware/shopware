<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
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

    /**
     * @param ProductDetailPageRequest $request
     * @param CheckoutContext          $context
     *
     * @return ProductDetailPageStruct
     */
    public function load(ProductDetailPageRequest $request, CheckoutContext $context): ProductDetailPageStruct
    {
        $page = new ProductDetailPageStruct();
        $page->setProductDetail(
            $this->productDetailPageletLoader->load($request->getProductDetailRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            ProductDetailPageLoadedEvent::NAME,
            new ProductDetailPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
