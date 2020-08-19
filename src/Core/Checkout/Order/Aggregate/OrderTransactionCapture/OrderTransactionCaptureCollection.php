<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(OrderTransactionCaptureEntity $entity)
 * @method void                               set(string $key, OrderTransactionCaptureEntity $entity)
 * @method OrderTransactionCaptureEntity[]    getIterator()
 * @method OrderTransactionCaptureEntity[]    getElements()
 * @method OrderTransactionCaptureEntity|null get(string $key)
 * @method OrderTransactionCaptureEntity|null first()
 * @method OrderTransactionCaptureEntity|null last()
 */
class OrderTransactionCaptureCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_collection';
    }

    public function filterByExternalReference(string $externalReference): self
    {
        return $this->filter(function (OrderTransactionCaptureEntity $orderTransactionCapture) use ($externalReference) {
            return $orderTransactionCapture->getExternalReference() === $externalReference;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureEntity::class;
    }
}
