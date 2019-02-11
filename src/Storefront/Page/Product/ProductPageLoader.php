<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductPageLoader implements PageLoaderInterface
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->productRepository = $productRepository;
    }

    public function load(InternalRequest $request, CheckoutContext $context): ProductPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = ProductPage::createFrom($page);

        $productId = $request->requireGet('productId');

        $criteria = new Criteria([$productId]);

        $product = $this->productRepository->read($criteria, $context)
            ->get($productId);

        $page->setProduct($product);

        $this->eventDispatcher->dispatch(
            ProductPageLoadedEvent::NAME,
            new ProductPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
