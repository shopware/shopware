<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderStateCollection extends EntityCollection
{
    /**
     * @var OrderStateEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateEntity
    {
        return parent::get($id);
    }

    public function current(): OrderStateEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return OrderStateEntity::class;
    }
}
