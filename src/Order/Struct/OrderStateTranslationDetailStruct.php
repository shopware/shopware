<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class OrderStateTranslationDetailStruct extends OrderStateTranslationBasicStruct
{
    /**
     * @var OrderStateBasicStruct
     */
    protected $orderState;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getOrderState(): OrderStateBasicStruct
    {
        return $this->orderState;
    }

    public function setOrderState(OrderStateBasicStruct $orderState): void
    {
        $this->orderState = $orderState;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
