<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Struct;


use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;
use Shopware\System\Country\Struct\CountryBasicStruct;

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
