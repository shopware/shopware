<?php declare(strict_types=1);

namespace Shopware\Area\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;

class AreaDetailStruct extends AreaBasicStruct
{
    /**
     * @var string[]
     */
    protected $countryUuids = [];

    /**
     * @var AreaCountryBasicCollection
     */
    protected $countries;

    public function __construct()
    {
        $this->countries = new AreaCountryBasicCollection();
    }

    public function getCountryUuids(): array
    {
        return $this->countryUuids;
    }

    public function setCountryUuids(array $countryUuids): void
    {
        $this->countryUuids = $countryUuids;
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        return $this->countries;
    }

    public function setCountries(AreaCountryBasicCollection $countries): void
    {
        $this->countries = $countries;
    }
}
