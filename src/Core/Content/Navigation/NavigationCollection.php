<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class NavigationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NavigationEntity::class;
    }
}
