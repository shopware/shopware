<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\ORM\EntityCollection;

class ListingSortingCollection extends EntityCollection
{
    /**
     * @var ListingSortingStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingSortingStruct
    {
        return parent::get($id);
    }

    public function current(): ListingSortingStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingStruct::class;
    }
}
