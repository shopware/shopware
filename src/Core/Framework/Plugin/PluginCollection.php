<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PluginCollection extends EntityCollection
{
    public function first(): ?PluginEntity
    {
        return parent::first();
    }

    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
