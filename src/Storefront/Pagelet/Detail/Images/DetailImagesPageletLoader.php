<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Detail\Images;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DetailImagesPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(StorefrontProductRepository $productRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws ProductNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function load(InternalRequest $request, SalesChannelContext $context): Struct
    {
        $page = new DetailImagesPagelet();

        $productId = $request->requireGet('productId');

        $criteria = new Criteria([$productId]);

        $product = $this->productRepository->read($criteria, $context)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        $page->setProductMedia($product->getMedia());

        $this->eventDispatcher->dispatch(
            DetailImagesPageletLoadedEvent::NAME,
            new DetailImagesPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
