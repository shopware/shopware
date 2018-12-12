<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingSortingCollection extends EntityCollection
{
    /**
     * @var ListingSortingEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingSortingEntity
    {
        return parent::get($id);
    }

    public function current(): ListingSortingEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingEntity::class;
    }
}
