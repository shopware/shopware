<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingSortingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ListingSortingEntity::class;
    }
}
