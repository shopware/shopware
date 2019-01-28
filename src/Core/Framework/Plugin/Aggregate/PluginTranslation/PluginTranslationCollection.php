<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PluginTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PluginTranslationEntity::class;
    }
}
