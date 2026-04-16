<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * Ensures product indexing (including product stream mapping) is triggered
 * when product-category assignments change via the category entity.
 *
 * The DAL's automatic mapping-parent resolution should already create synthetic
 * product.written events from product_category writes, triggering the ProductIndexer.
 * This subscriber acts as a defensive safety net for cases where the automatic
 * resolution does not propagate correctly (e.g. certain write paths or nested
 * association updates). The double-indexing cost is negligible since product
 * indexing is idempotent.
 */
#[Package('inventory')]
class ProductCategoryWrittenSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_CATEGORY_WRITTEN_EVENT => 'onProductCategoryWritten',
            ProductEvents::PRODUCT_CATEGORY_DELETED_EVENT => 'onProductCategoryWritten',
        ];
    }

    public function onProductCategoryWritten(EntityWrittenEvent $event): void
    {
        $productIds = [];

        foreach ($event->getWriteResults() as $result) {
            $primaryKey = $result->getPrimaryKey();

            if (!\is_array($primaryKey) || !isset($primaryKey['productId'])) {
                continue;
            }

            $productIds[$primaryKey['productId']] = true;
        }

        if ($productIds === []) {
            return;
        }

        $message = new ProductIndexingMessage(array_keys($productIds));
        $message->setIndexer('product.indexer');

        $this->messageBus->dispatch($message);
    }
}
