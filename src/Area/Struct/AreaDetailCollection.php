<?php declare(strict_types=1);

namespace Shopware\Area\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;

class AreaDetailCollection extends AreaBasicCollection
{
    /**
     * @var AreaDetailStruct[]
     */
    protected $elements = [];

    public function getCountryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        $collection = new AreaCountryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountries()->getElements());
        }

        return $collection;
    }
}
