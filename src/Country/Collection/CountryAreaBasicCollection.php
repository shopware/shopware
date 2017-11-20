<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Country\Struct\CountryAreaBasicStruct;

class CountryAreaBasicCollection extends EntityCollection
{
    /**
     * @var CountryAreaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CountryAreaBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryAreaBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaBasicStruct::class;
    }
}
