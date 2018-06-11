<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Struct;

use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

class DiscountSurchargeTranslationDetailStruct extends DiscountSurchargeTranslationBasicStruct
{
    /**
     * @var DiscountSurchargeBasicStruct
     */
    protected $discountSurcharge;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getDiscountSurcharge(): DiscountSurchargeBasicStruct
    {
        return $this->discountSurcharge;
    }

    public function setDiscountSurcharge(DiscountSurchargeBasicStruct $discountSurcharge): void
    {
        $this->discountSurcharge = $discountSurcharge;
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
