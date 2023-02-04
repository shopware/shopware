<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerWishlistLoaderCriteriaEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerWishlistProductListingResultEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class LoadWishlistRoute extends AbstractLoadWishlistRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    public function getDecorated(): AbstractLoadWishlistRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/customer/wishlist', name: 'store-api.customer.wishlist.load', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_entity' => 'product'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): LoadWishlistRouteResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $wishlist = $this->loadWishlist($context, $customer->getId());
        $products = $this->loadProducts($wishlist->getId(), $criteria, $context, $request);

        return new LoadWishlistRouteResponse($wishlist, $products);
    }

    private function loadWishlist(SalesChannelContext $context, string $customerId): CustomerWishlistEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
        ]));

        $wishlist = $this->wishlistRepository->search($criteria, $context->getContext());

        if ($wishlist->first() === null) {
            throw new CustomerWishlistNotFoundException();
        }

        return $wishlist->first();
    }

    private function loadProducts(string $wishlistId, Criteria $criteria, SalesChannelContext $context, Request $request): EntitySearchResult
    {
        $criteria->addFilter(
            new EqualsFilter('wishlists.wishlistId', $wishlistId)
        );

        $criteria->addSorting(
            new FieldSorting('wishlists.updatedAt', FieldSorting::DESCENDING)
        );

        $criteria->addSorting(
            new FieldSorting('wishlists.createdAt', FieldSorting::DESCENDING)
        );

        $criteria = $this->handleAvailableStock($criteria, $context);

        $event = new CustomerWishlistLoaderCriteriaEvent($criteria, $context);
        $this->eventDispatcher->dispatch($event);

        $products = $this->productRepository->search($criteria, $context);

        $event = new CustomerWishlistProductListingResultEvent($request, $products, $context);
        $this->eventDispatcher->dispatch($event);

        return $products;
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): Criteria
    {
        $hide = $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        );

        if (!$hide) {
            return $criteria;
        }

        $closeoutFilter = $this->productCloseoutFilterFactory->create($context);
        $criteria->addFilter($closeoutFilter);

        return $criteria;
    }
}
