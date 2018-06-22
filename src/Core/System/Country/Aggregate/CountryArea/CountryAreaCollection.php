<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea;

use Shopware\Core\Framework\ORM\EntityCollection;

class CountryAreaCollection extends EntityCollection
{
    /**
     * @var CountryAreaStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryAreaStruct
    {
        return parent::get($id);
    }

    public function current(): CountryAreaStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaStruct::class;
    }
}
