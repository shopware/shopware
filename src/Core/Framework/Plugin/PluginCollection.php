<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PluginCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
