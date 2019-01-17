<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletLoadedEvent;

class ProductDetailPageLoadedEvent extends NestedEvent
{
    public const NAME = 'product-detail.page.loaded';

    /**
     * @var ProductDetailPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ProductDetailPageRequest
     */
    protected $request;

    public function __construct(ProductDetailPageStruct $page, CheckoutContext $context, ProductDetailPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new ProductDetailPageletLoadedEvent($this->page->getProductDetail(), $this->context, $this->request->getProductDetailRequest()),
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPage(): ProductDetailPageStruct
    {
        return $this->page;
    }

    public function getRequest(): ProductDetailPageRequest
    {
        return $this->request;
    }
}
