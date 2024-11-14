<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class WishlistPageLoader
{
    private const LIMIT = 24;

    private const DEFAULT_PAGE = 1;

    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractLoadWishlistRoute $wishlistLoadRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer): WishlistPage
    {
        $criteria = $this->createCriteria($request);
        $this->eventDispatcher->dispatch(new WishListPageProductCriteriaEvent($criteria, $context, $request));

        $page = $this->genericLoader->load($request, $context);
        $page = WishlistPage::createFrom($page);

        try {
            $page->setWishlist($this->wishlistLoadRoute->load($request, $context, $criteria, $customer));
        } catch (CustomerWishlistNotFoundException) {
            $page->setWishlist(
                new LoadWishlistRouteResponse(
                    new CustomerWishlistEntity(),
                    new EntitySearchResult(
                        'wishlist',
                        0,
                        new ProductCollection(),
                        null,
                        $criteria,
                        $context->getContext()
                    )
                )
            );
        }

        $this->eventDispatcher->dispatch(
            new WishlistPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->query->get('limit');
        $limit = $limit ? (int) $limit : self::LIMIT;
        $page = $request->query->get('p');
        $page = $page ? (int) $page : self::DEFAULT_PAGE;
        $offset = $limit * ($page - 1);

        return (new Criteria())
            ->setTitle('wishlist::page')
            ->addSorting(new FieldSorting('wishlists.updatedAt', FieldSorting::ASCENDING))
            ->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setLimit($limit)
            ->setOffset($offset)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }
}
