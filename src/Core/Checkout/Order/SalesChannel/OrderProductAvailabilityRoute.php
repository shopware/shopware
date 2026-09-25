<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Order line items keep referencing products that were deactivated, hidden or deleted, so the order alone
 * cannot tell whether one is still shown in this sales channel. Answers that as the
 * `productAvailability` line item extension.
 *
 * @internal
 */
#[Package('checkout')]
class OrderProductAvailabilityRoute extends AbstractOrderRoute
{
    public const LINE_ITEM_EXTENSION = 'productAvailability';

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly AbstractOrderRoute $decorated,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        $response = $this->decorated->load($request, $context, $criteria);

        $this->addAvailability($response->getOrders()->getEntities()->getElements(), $context);

        return $response;
    }

    /**
     * @param array<OrderEntity> $orders
     */
    private function addAvailability(array $orders, SalesChannelContext $context): void
    {
        $productIds = [];

        foreach ($orders as $order) {
            foreach ($this->getProductLineItems($order) as $lineItem) {
                $productId = $lineItem->getProductId();

                if ($productId !== null) {
                    $productIds[$productId] = true;
                }
            }
        }

        $visible = [];

        // nothing to look up, but the line items below still need the extension
        if ($productIds !== []) {
            $criteria = new Criteria(array_keys($productIds));
            $criteria->setTitle('order-line-item::product-availability');

            // mirrors ProductDetailRoute, so the answer matches whether the detail page resolves
            if ($this->systemConfigService->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $context->getSalesChannelId())) {
                $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
            }

            // one query, and searchIds() reads no entities so no price calculation runs.
            // the sales channel repository filters on active and visibility.
            $visible = array_flip($this->productRepository->searchIds($criteria, $context)->getIds());
        }

        foreach ($orders as $order) {
            foreach ($this->getProductLineItems($order) as $lineItem) {
                $productId = $lineItem->getProductId();

                $lineItem->addExtension(self::LINE_ITEM_EXTENSION, new ArrayStruct([
                    'visible' => $productId !== null && isset($visible[$productId]),
                ]));
            }
        }
    }

    /**
     * @return list<OrderLineItemEntity>
     */
    private function getProductLineItems(OrderEntity $order): array
    {
        $lineItems = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $lineItems[] = $lineItem;
            }
        }

        return $lineItems;
    }
}
