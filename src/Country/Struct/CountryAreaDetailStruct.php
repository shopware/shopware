<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Country\Collection\CountryBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryAreaDetailStruct extends CountryAreaBasicStruct
{
    /**
     * @var CountryBasicCollection
     */
    protected $countries;

    /**
     * @var CountryAreaTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct()
    {
        $this->countries = new CountryBasicCollection();

        $this->translations = new CountryAreaTranslationBasicCollection();

        $this->taxAreaRules = new TaxAreaRuleBasicCollection();
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

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(TaxAreaRuleBasicCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }
}
