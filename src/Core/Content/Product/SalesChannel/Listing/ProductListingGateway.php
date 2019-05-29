<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingGateway implements ProductListingGatewayInterface
{
    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.displayInListing', true));

        $this->handleCategoryFilter($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductEvents::PRODUCT_LISTING_CRITERIA,
            new ProductListingCriteriaEvent($request, $criteria, $context)
        );

        $result = $this->productRepository->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductEvents::PRODUCT_LISTING_RESULT,
            new ProductListingResultEvent($request, $result, $context)
        );

        return $result;
    }

    private function handleCategoryFilter(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        $params = $request->attributes->get('_route_params');

        if ($params && isset($params['navigationId'])) {
            $navigationId = $params['navigationId'];
        }

        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $navigationId));
    }
}
