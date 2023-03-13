<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\WishlistMergedEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class MergeWishlistProductRoute extends AbstractMergeWishlistProductRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): AbstractMergeWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/customer/wishlist/merge', name: 'store-api.customer.wishlist.merge', methods: ['POST'], defaults: ['_loginRequired' => true])]
    public function merge(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $upsertData = $this->buildUpsertProducts($data, $wishlistId, $context);

        $this->wishlistRepository->upsert([[
            'id' => $wishlistId,
            'customerId' => $customer->getId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'products' => $upsertData,
        ]], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistMergedEvent($upsertData, $context));

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, string $customerId): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
        ]));

        $wishlistIds = $this->wishlistRepository->searchIds($criteria, $context->getContext());

        return $wishlistIds->firstId() ?? Uuid::randomHex();
    }

    /**
     * @return array<array{id: string, productId?: string, productVersionId?: Defaults::LIVE_VERSION}>
     */
    private function buildUpsertProducts(RequestDataBag $data, string $wishlistId, SalesChannelContext $context): array
    {
        $ids = array_unique(array_filter($data->get('productIds')->all()));

        if (\count($ids) === 0) {
            return [];
        }

        /** @var array<string> $ids */
        $ids = $this->productRepository->searchIds(new Criteria($ids), $context)->getIds();

        $customerProducts = $this->loadCustomerProducts($wishlistId, $ids);

        $upsertData = [];

        /** @var string $id * */
        foreach ($ids as $id) {
            if (\array_key_exists($id, $customerProducts)) {
                $upsertData[] = [
                    'id' => $customerProducts[$id],
                ];

                continue;
            }

            $upsertData[] = [
                'id' => Uuid::randomHex(),
                'productId' => $id,
                'productVersionId' => Defaults::LIVE_VERSION,
            ];
        }

        return $upsertData;
    }

    /**
     * @param array<string> $productIds
     *
     * @return array<string, string>
     */
    private function loadCustomerProducts(string $wishlistId, array $productIds): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(`product_id`)) as `product_id`',
            'LOWER(HEX(`id`)) as id',
        ]);
        $query->from('`customer_wishlist_product`');
        $query->where('`customer_wishlist_id` = :id');
        $query->andWhere('`product_id` IN (:productIds)');
        $query->setParameter('id', Uuid::fromHexToBytes($wishlistId));
        $query->setParameter('productIds', Uuid::fromHexToBytesList($productIds), ArrayParameterType::STRING);
        $result = $query->executeQuery();

        /** @var array<string, string> $values */
        $values = $result->fetchAllKeyValue();

        return $values;
    }
}
