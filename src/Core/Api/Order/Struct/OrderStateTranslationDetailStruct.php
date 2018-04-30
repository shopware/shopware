<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class OrderStateTranslationDetailStruct extends OrderStateTranslationBasicStruct
{
    /**
     * @var OrderStateBasicStruct
     */
    protected $orderState;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
