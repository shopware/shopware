<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(PluginEntity $entity)
 * @method void              set(string $key, PluginEntity $entity)
 * @method PluginEntity[]    getIterator()
 * @method PluginEntity[]    getElements()
 * @method PluginEntity|null get(string $key)
 * @method PluginEntity|null first()
 * @method PluginEntity|null last()
 */
class PluginCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'plugin_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginEntity::class;
    }
}
