<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Order\Struct\OrderStateBasicStruct;

class OrderStateBasicCollection extends EntityCollection
{
    /**
     * @var OrderStateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderStateBasicStruct
    {
        return parent::get($uuid);
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
