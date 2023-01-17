<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderLineItemDownloadEntity>
 */
class OrderLineItemDownloadCollection extends EntityCollection
{
    public function filterByOrderLineItemId(string $id): self
    {
        return $this->filter(function (OrderLineItemDownloadEntity $orderLineItemDownloadEntity) use ($id) {
            return $orderLineItemDownloadEntity->getOrderLineItemId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'order_line_item_download_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemDownloadEntity::class;
    }
}
