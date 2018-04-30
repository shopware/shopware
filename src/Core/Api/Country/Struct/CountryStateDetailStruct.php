<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryStateTranslationBasicCollection;

class CountryStateDetailStruct extends CountryStateBasicStruct
{
    /**
     * @var CountryBasicStruct
     */
    protected $country;

    /**
     * @var CountryStateTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new CountryStateTranslationBasicCollection();
    }

    public function getCountry(): CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(CountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryStateTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
