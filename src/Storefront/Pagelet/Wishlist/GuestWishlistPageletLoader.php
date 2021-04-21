<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class GuestWishlistPageletLoader
{
    private const LIMIT = 100;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractProductListRoute $productListRoute;

    private SystemConfigService $systemConfigService;

    public function __construct(
        AbstractProductListRoute $productListRoute,
        SystemConfigService $systemConfigService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productListRoute = $productListRoute;
        $this->systemConfigService = $systemConfigService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): GuestWishlistPagelet
    {
        $page = new GuestWishlistPagelet();

        $criteria = $this->createCriteria($request, $context);
        $this->eventDispatcher->dispatch(new GuestWishListPageletProductCriteriaEvent($criteria, $context, $request));

        if (empty($criteria->getIds())) {
            $response = new ProductListResponse(new EntitySearchResult(
                'wishlist',
                0,
                new ProductCollection(),
                null,
                $criteria,
                $context->getContext()
            ));
        } else {
            $response = $this->productListRoute->load($criteria, $context);
        }

        $page->setSearchResult($response);

        $this->eventDispatcher->dispatch(new GuestWishlistPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $criteria = new Criteria();

        $productIds = $request->get('productIds', []);

        if (!\is_array($productIds)) {
            throw new \InvalidArgumentException('Argument $productIds is not an array');
        }

        $productIds = array_filter($productIds, static function ($productId) {
            return Uuid::isValid($productId);
        });

        $criteria->setLimit(self::LIMIT);
        $criteria->setIds($productIds);

        $criteria->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(new ProductCloseoutFilter());
        }

        return $criteria;
    }
}
