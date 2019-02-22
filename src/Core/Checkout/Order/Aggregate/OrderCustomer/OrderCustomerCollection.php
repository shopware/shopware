<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerCollection;
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

    protected function getExpectedClass(): string
    {
        return OrderCustomerEntity::class;
    }
}
