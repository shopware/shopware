<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection;


use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeBasicCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeTranslationDetailStruct;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

class DiscountSurchargeTranslationDetailCollection extends DiscountSurchargeTranslationBasicCollection
{
    /**
     * @var DiscountSurchargeTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getDiscountSurcharges(): DiscountSurchargeBasicCollection
    {
        return new DiscountSurchargeBasicCollection(
            $this->fmap(function (DiscountSurchargeTranslationDetailStruct $discountSurchargeTranslation) {
                return $discountSurchargeTranslation->getDiscountSurcharge();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (DiscountSurchargeTranslationDetailStruct $discountSurchargeTranslation) {
                return $discountSurchargeTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeTranslationDetailStruct::class;
    }
}
