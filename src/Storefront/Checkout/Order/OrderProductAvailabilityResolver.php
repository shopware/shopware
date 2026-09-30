<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Order;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Order line items keep referencing products that were deactivated, hidden or deleted, so the order alone cannot
 * tell whether one is still shown in this sales channel. Answers that as the `productAvailability` line item
 * extension, for the pages that render order line items.
 */
#[Package('checkout')]
class OrderProductAvailabilityResolver
{
    public const LINE_ITEM_EXTENSION = 'productAvailability';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductListRoute $productListRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    /**
     * @param iterable<OrderEntity> $orders
     */
    public function addAvailability(iterable $orders, SalesChannelContext $context): void
    {
        $lineItems = [];
        $productIds = [];

        foreach ($orders as $order) {
            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                    continue;
                }

                $lineItems[] = $lineItem;

                $productId = $lineItem->getProductId();

                if ($productId !== null) {
                    $productIds[$productId] = true;
                }
            }
        }

        // an empty id list leaves the criteria unfiltered and unlimited, which would read the whole catalogue
        $visible = $productIds === [] ? [] : $this->getVisibleProductIds(array_keys($productIds), $context);

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getProductId();

            $lineItem->addExtension(self::LINE_ITEM_EXTENSION, new ArrayStruct([
                'visible' => $productId !== null && isset($visible[$productId]),
            ]));
        }
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, int>
     */
    private function getVisibleProductIds(array $productIds, SalesChannelContext $context): array
    {
        $criteria = new Criteria($productIds);
        $criteria->setTitle('order-line-item::product-availability');

        // ids only, so the definition skips its price, unit, delivery time, cover and tax associations, which is
        // what keeps the price calculator out of this lookup
        $criteria->addFields(['id']);

        // mirrors ProductDetailRoute, so the answer matches whether the detail page resolves
        if ($this->systemConfigService->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $context->getSalesChannelId())) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
        }

        // getProducts() would only work while the result is empty, a partial load carries no product collection
        $result = $this->productListRoute->load($criteria, $context)->getObject();

        return array_flip($result->getEntities()->getIds());
    }
}
