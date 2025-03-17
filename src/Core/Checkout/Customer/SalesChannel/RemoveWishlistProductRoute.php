<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\WishlistProductRemovedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class RemoveWishlistProductRoute extends AbstractRemoveWishlistProductRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWishlistCollection> $wishlistRepository
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly EntityRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractRemoveWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/customer/wishlist/delete/{productId}', name: 'store-api.customer.wishlist.delete', methods: ['DELETE'], defaults: ['_loginRequired' => true])]
    public function delete(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannelId())) {
            throw CustomerException::customerWishlistNotActivated();
        }

        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $wishlistProductId = $this->getWishlistProductId($wishlistId, $productId, $context);

        $this->productRepository->delete([
            [
                'id' => $wishlistProductId,
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistProductRemovedEvent($wishlistId, $productId, $context));

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, string $customerId): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            ]));

        $wishlistId = $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistId) {
            throw CustomerException::customerWishlistNotFound();
        }

        return $wishlistId;
    }

    private function getWishlistProductId(string $wishlistId, string $productId, SalesChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('wishlistId', $wishlistId),
                new EqualsFilter('productId', $productId),
                new EqualsFilter('productVersionId', Defaults::LIVE_VERSION),
            ]));

        $wishlistProductId = $this->productRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistProductId) {
            throw CustomerException::wishlistProductNotFound($productId);
        }

        return $wishlistProductId;
    }
}
