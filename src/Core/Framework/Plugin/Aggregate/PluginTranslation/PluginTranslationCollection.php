<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(PluginTranslationEntity $entity)
 * @method void                         set(string $key, PluginTranslationEntity $entity)
 * @method PluginTranslationEntity[]    getIterator()
 * @method PluginTranslationEntity[]    getElements()
 * @method PluginTranslationEntity|null get(string $key)
 * @method PluginTranslationEntity|null first()
 * @method PluginTranslationEntity|null last()
 */
class PluginTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'plugin_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PluginTranslationEntity::class;
    }
}
