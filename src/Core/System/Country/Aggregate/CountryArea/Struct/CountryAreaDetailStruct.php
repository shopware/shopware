<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Struct;

use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Core\System\Country\Collection\CountryBasicCollection;

class CountryAreaDetailStruct extends CountryAreaBasicStruct
{
    /**
     * @var CountryBasicCollection
     */
    protected $countries;

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->countries = new CountryBasicCollection();

        $this->translations = new CountryAreaTranslationBasicCollection();
    }

    public function getCountries(): CountryBasicCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryBasicCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryAreaTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
