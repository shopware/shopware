<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderStateCollection extends EntityCollection
{
    /**
     * @var OrderStateStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateStruct
    {
        return parent::get($id);
    }

    public function current(): OrderStateStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return OrderStateStruct::class;
    }
}
