<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class GuestWishlistPageletLoader
{
    private const LIMIT = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductListRoute $productListRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): GuestWishlistPagelet
    {
        $page = new GuestWishlistPagelet();

        $productsIds = $this->extractProductIds($request);
        $criteria = $this->createCriteria($productsIds, $context);
        $this->eventDispatcher->dispatch(new GuestWishListPageletProductCriteriaEvent($criteria, $context, $request));

        if (!empty($productsIds)) {
            $response = $this->productListRoute->load($criteria, $context);
        } else {
            $response = new ProductListResponse(new EntitySearchResult(
                'wishlist',
                0,
                new ProductCollection(),
                null,
                $criteria,
                $context->getContext()
            ));
        }

        $page->setSearchResult($response);

        $this->eventDispatcher->dispatch(new GuestWishlistPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    /**
     * @return array<string>
     */
    private function extractProductIds(Request $request): array
    {
        $productIds = $request->get('productIds', []);

        if (!\is_array($productIds)) {
            throw RoutingException::missingRequestParameter('productIds');
        }

        /** @var array<string> $productIds */
        return array_filter($productIds, static fn (string $productId) => Uuid::isValid($productId));
    }

    /**
     * @param array<string> $productIds
     */
    private function createCriteria(array $productIds, SalesChannelContext $context): Criteria
    {
        $criteria = new Criteria();

        $criteria->setLimit(self::LIMIT);

        if (!empty($productIds)) {
            $criteria->setIds($productIds);
        }

        $criteria->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $closeoutFilter = $this->productCloseoutFilterFactory->create($context);
            $criteria->addFilter($closeoutFilter);
        }

        return $criteria;
    }
}
