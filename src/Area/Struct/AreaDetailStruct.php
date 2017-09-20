<?php declare(strict_types=1);

namespace Shopware\Area\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;

class AreaDetailStruct extends AreaBasicStruct
{
    /**
     * @var AreaCountryBasicCollection
     */
    protected $countries;

    public function __construct()
    {
        $this->countries = new AreaCountryBasicCollection();
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
