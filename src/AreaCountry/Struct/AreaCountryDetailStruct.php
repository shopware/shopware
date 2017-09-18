<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Struct;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;

class AreaCountryDetailStruct extends AreaCountryBasicStruct
{
    /**
     * @var string[]
     */
    protected $stateUuids = [];

    /**
     * @var AreaCountryStateBasicCollection
     */
    protected $states;

    public function __construct()
    {
        $this->states = new AreaCountryStateBasicCollection();
    }

    public function getStateUuids(): array
    {
        return $this->stateUuids;
    }

    public function setStateUuids(array $stateUuids): void
    {
        $this->stateUuids = $stateUuids;
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
