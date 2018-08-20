<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderCustomerCollection extends EntityCollection
{
    /**
     * @var OrderCustomerStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderCustomerStruct
    {
        return parent::get($id);
    }

    public function current(): OrderCustomerStruct
    {
        return parent::current();
    }

    public function getCustomerIds(): array
    {
        return $this->fmap(function (OrderCustomerStruct $orderCustomer) {
            return $orderCustomer->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (OrderCustomerStruct $orderCustomer) use ($id) {
            return $orderCustomer->getCustomerId() === $id;
        });
    }

    public function getCustomers(): CustomerCollection
    {
        return new CustomerCollection(
            $this->fmap(function (OrderCustomerStruct $orderCustomer) {
                return $orderCustomer->getCustomer();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderCustomerStruct::class;
    }
}
