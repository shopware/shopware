<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(OrderCustomerEntity $entity)
 * @method void                     set(string $key, OrderCustomerEntity $entity)
 * @method OrderCustomerEntity[]    getIterator()
 * @method OrderCustomerEntity[]    getElements()
 * @method OrderCustomerEntity|null get(string $key)
 * @method OrderCustomerEntity|null first()
 * @method OrderCustomerEntity|null last()
 */
class OrderCustomerCollection extends EntityCollection
{
    public function getCustomerIds(): array
    {
        return $this->fmap(function (OrderCustomerEntity $orderCustomer) {
            return $orderCustomer->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (OrderCustomerEntity $orderCustomer) use ($id) {
            return $orderCustomer->getCustomerId() === $id;
        });
    }

    public function getCustomers(): CustomerCollection
    {
        return new CustomerCollection(
            $this->fmap(function (OrderCustomerEntity $orderCustomer) {
                return $orderCustomer->getCustomer();
            })
        );
    }

    public function getLastOrderDate(): ?\DateTimeInterface
    {
        $lastOrderDate = null;

        foreach ($this->getOrders() as $order) {
            if (!$lastOrderDate || $order->getOrderDate() < $lastOrderDate) {
                $lastOrderDate = $order->getOrderDate();
            }
        }

        return $lastOrderDate;
    }

    public function getOrders(): OrderCollection
    {
        $orders = new OrderCollection();
        foreach ($this->getElements() as $orderCustomer) {
            if ($orderCustomer->getOrder() === null) {
                continue;
            }
            $orders->add($orderCustomer->getOrder());
        }

        return $orders;
    }

    public function getApiAlias(): string
    {
        return 'order_customer_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderCustomerEntity::class;
    }
}
