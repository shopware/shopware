<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Order line items keep referencing their product after it was deactivated, made invisible for the sales channel
 * or deleted, so templates cannot tell from the order alone whether the product detail page still resolves.
 *
 * @internal
 */
#[Package('checkout')]
class OrderProductAvailabilitySubscriber implements EventSubscriberInterface
{
    public const LINE_ITEM_EXTENSION = 'productAvailable';

    public const ORDER_EXTENSION = 'reorderable';

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly SalesChannelRepository $productRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AccountOrderPageLoadedEvent::class => 'onAccountOrderPageLoaded',
            AccountOverviewPageLoadedEvent::class => 'onAccountOverviewPageLoaded',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishPageLoaded',
        ];
    }

    public function onAccountOrderPageLoaded(AccountOrderPageLoadedEvent $event): void
    {
        $this->addAvailability(
            array_values($event->getPage()->getOrders()->getEntities()->getElements()),
            $event->getSalesChannelContext()
        );
    }

    public function onAccountOverviewPageLoaded(AccountOverviewPageLoadedEvent $event): void
    {
        $order = $event->getPage()->getNewestOrder();

        if ($order === null) {
            return;
        }

        $this->addAvailability([$order], $event->getSalesChannelContext());
    }

    public function onCheckoutFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $this->addAvailability([$event->getPage()->getOrder()], $event->getSalesChannelContext());
    }

    /**
     * @param list<OrderEntity> $orders
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

        $available = [];

        // an order whose products were all deleted has nothing left to look up, but its line items still
        // need the extension, otherwise templates fall back to treating them as available
        if ($productIds !== []) {
            $criteria = new Criteria(array_keys($productIds));
            $criteria->setTitle('order-line-item::product-availability');

            // one query per page, and searchIds() never reads entities, so no product price calculation is
            // triggered. the sales channel repository applies the product available filter, which covers
            // the active flag and the sales channel visibility.
            $available = array_flip($this->productRepository->searchIds($criteria, $context)->getIds());
        }

        foreach ($orders as $order) {
            $reorderable = false;

            foreach ($this->getProductLineItems($order) as $lineItem) {
                $productId = $lineItem->getProductId();
                $isAvailable = $productId !== null && isset($available[$productId]);
                $reorderable = $reorderable || $isAvailable;

                $lineItem->addExtension(self::LINE_ITEM_EXTENSION, new ArrayStruct(['available' => $isAvailable]));
            }

            $order->addExtension(self::ORDER_EXTENSION, new ArrayStruct(['available' => $reorderable]));
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
