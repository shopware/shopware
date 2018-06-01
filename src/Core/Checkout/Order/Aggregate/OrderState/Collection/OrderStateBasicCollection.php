<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderStateBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderStateBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return OrderStateBasicStruct::class;
    }
}
