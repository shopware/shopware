<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductStreamCollection extends EntityCollection
{
    public function sortByPriority(): void
    {
        $this->sort(function (ProductStreamEntity $a, ProductStreamEntity $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
