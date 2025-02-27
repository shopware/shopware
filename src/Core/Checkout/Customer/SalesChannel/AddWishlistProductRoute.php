<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\WishlistProductAddedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class AddWishlistProductRoute extends AbstractAddWishlistProductRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWishlistCollection> $wishlistRepository
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractAddWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/customer/wishlist/add/{productId}', name: 'store-api.customer.wishlist.add', methods: ['POST'], defaults: ['_loginRequired' => true])]
    public function add(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannelId())) {
            throw CustomerException::customerWishlistNotActivated();
        }

        $this->validateProduct($productId, $context);
        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $this->wishlistRepository->upsert([
            [
                'id' => $wishlistId,
                'customerId' => $customer->getId(),
                'salesChannelId' => $context->getSalesChannelId(),
                'products' => [
                    [
                        'productId' => $productId,
                        'productVersionId' => Defaults::LIVE_VERSION,
                    ],
                ],
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistProductAddedEvent($wishlistId, $productId, $context));

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

        return $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId() ?? Uuid::randomHex();
    }

    private function validateProduct(string $productId, SalesChannelContext $context): void
    {
        $total = $this->productRepository->searchIds(new Criteria([$productId]), $context)->getTotal();
        if ($total === 0) {
            throw CustomerException::productNotFound($productId);
        }
    }
}
