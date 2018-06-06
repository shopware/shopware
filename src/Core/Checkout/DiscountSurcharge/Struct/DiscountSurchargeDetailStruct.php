<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Struct;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationBasicCollection;

class DiscountSurchargeDetailStruct extends DiscountSurchargeBasicStruct
{
    /**
     * @var \Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationBasicCollection
     */
    protected $translations;

    public function getTranslations(): DiscountSurchargeTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(DiscountSurchargeTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
