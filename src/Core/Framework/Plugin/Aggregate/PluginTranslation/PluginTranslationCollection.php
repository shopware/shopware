<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PluginTranslationEntity>
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
