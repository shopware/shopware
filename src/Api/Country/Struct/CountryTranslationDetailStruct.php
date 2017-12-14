<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CountryTranslationDetailStruct extends CountryTranslationBasicStruct
{
    /**
     * @var CountryBasicStruct
     */
    protected $country;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCountry(): CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(CountryBasicStruct $country): void
    {
        $this->country = $country;
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
