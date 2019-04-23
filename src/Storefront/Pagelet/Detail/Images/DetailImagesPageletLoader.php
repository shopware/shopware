<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Detail\Images;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class DetailImagesPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws ProductNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $context): Struct
    {
        $page = new DetailImagesPagelet();

        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId');
        }

        $criteria = new Criteria([$productId]);

        $product = $this->productRepository->search($criteria, $context->getContext())->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        if ($product->getMedia()) {
            $page->setProductMedia($product->getMedia());
        }

        $this->eventDispatcher->dispatch(
            DetailImagesPageletLoadedEvent::NAME,
            new DetailImagesPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
