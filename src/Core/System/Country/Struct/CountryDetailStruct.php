<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryStateBasicCollection;
use Shopware\System\Country\Collection\CountryTranslationBasicCollection;

class CountryDetailStruct extends CountryBasicStruct
{
    /**
     * @var CountryAreaBasicStruct|null
     */
    protected $area;

    /**
     * @var CountryStateBasicCollection
     */
    protected $states;

    /**
     * @var CountryTranslationBasicCollection
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
