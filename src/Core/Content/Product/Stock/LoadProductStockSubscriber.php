<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class LoadProductStockSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AbstractStockStorage $stockStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => ['salesChannelLoaded', 50],
            'sales_channel.product.partial_loaded' => ['salesChannelLoaded', 50],
        ];
    }

    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $stocks = $this->stockStorage->load(
            new StockLoadRequest($event->getIds()),
            $event->getSalesChannelContext()
        );

        foreach ($event->getEntities() as $product) {
            /** @var ProductEntity $product */
            $stock = $stocks->getStockForProductId($product->getId());

            if ($stock === null) {
                continue;
            }

            $product->assign([
                // required stock data
                'stock' => $stock->stock,
                'available' => $stock->available,
                // optional stock data
                'minPurchase' => $stock->minPurchase ?? $product->get('minPurchase'),
                'maxPurchase' => $stock->maxPurchase ?? $product->get('maxPurchase'),
                'isCloseout' => $stock->isCloseout ?? $product->get('isCloseout'),
            ]);

            // allow for arbitrary stock data to be added to the product
            $product->addExtension('stock_data', $stock);
        }
    }
}
