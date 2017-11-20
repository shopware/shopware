<?php declare(strict_types=1);

namespace Shopware\Listing\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Listing\Struct\ListingSortingBasicStruct;

class ListingSortingBasicCollection extends EntityCollection
{
    /**
     * @var ListingSortingBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ListingSortingBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ListingSortingBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingBasicStruct::class;
    }
}
