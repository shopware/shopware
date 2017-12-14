<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderStateTranslationBasicStruct;

class OrderStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var OrderStateTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderStateTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderStateTranslationBasicStruct
    {
        return parent::current();
    }

    public function getOrderStateUuids(): array
    {
        return $this->fmap(function (OrderStateTranslationBasicStruct $orderStateTranslation) {
            return $orderStateTranslation->getOrderStateUuid();
        });
    }

    public function filterByOrderStateUuid(string $uuid): OrderStateTranslationBasicCollection
    {
        return $this->filter(function (OrderStateTranslationBasicStruct $orderStateTranslation) use ($uuid) {
            return $orderStateTranslation->getOrderStateUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (OrderStateTranslationBasicStruct $orderStateTranslation) {
            return $orderStateTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): OrderStateTranslationBasicCollection
    {
        return $this->filter(function (OrderStateTranslationBasicStruct $orderStateTranslation) use ($uuid) {
            return $orderStateTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderStateTranslationBasicStruct::class;
    }
}
