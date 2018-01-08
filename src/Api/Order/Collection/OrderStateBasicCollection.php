<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderStateBasicStruct;

class OrderStateBasicCollection extends EntityCollection
{
    /**
     * @var OrderStateBasicStruct[]
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
