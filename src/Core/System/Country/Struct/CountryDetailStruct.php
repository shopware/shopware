<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaBasicStruct;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;

class CountryDetailStruct extends CountryBasicStruct
{
    /**
     * @var CountryAreaBasicStruct|null
     */
    protected $area;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection
     */
    protected $states;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->states = new CountryStateBasicCollection();

        $this->translations = new CountryTranslationBasicCollection();
    }

    public function getArea(): ?CountryAreaBasicStruct
    {
        return $this->area;
    }

    public function setArea(?CountryAreaBasicStruct $area): void
    {
        $this->area = $area;
    }

    public function getStates(): CountryStateBasicCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateBasicCollection $states): void
    {
        $this->states = $states;
    }

    public function getTranslations(): CountryTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
