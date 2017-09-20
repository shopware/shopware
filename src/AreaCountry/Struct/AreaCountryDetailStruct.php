<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Struct;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;

class AreaCountryDetailStruct extends AreaCountryBasicStruct
{
    /**
     * @var AreaCountryStateBasicCollection
     */
    protected $states;

    public function __construct()
    {
        $this->states = new AreaCountryStateBasicCollection();
    }

    public function getStates(): AreaCountryStateBasicCollection
    {
        return $this->states;
    }

    public function setStates(AreaCountryStateBasicCollection $states): void
    {
        $this->states = $states;
    }
}
