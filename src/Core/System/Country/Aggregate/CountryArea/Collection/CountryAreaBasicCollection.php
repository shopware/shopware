<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Collection;

use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class CountryAreaBasicCollection extends EntityCollection
{
    /**
     * @var CountryAreaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryAreaBasicStruct
    {
        return parent::get($id);
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
