<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PluginCollection extends EntityCollection
{
    /**
     * @var PluginEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? PluginEntity
    {
        return parent::get($id);
    }

    public function current(): PluginEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
