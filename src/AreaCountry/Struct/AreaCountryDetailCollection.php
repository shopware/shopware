<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Struct;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;

class AreaCountryDetailCollection extends AreaCountryBasicCollection
{
    /**
     * @var AreaCountryDetailStruct[]
     */
    protected $elements = [];

    public function getStateUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getStates()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getStates(): AreaCountryStateBasicCollection
    {
        $collection = new AreaCountryStateBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getStates()->getElements());
        }

        return $collection;
    }
}
