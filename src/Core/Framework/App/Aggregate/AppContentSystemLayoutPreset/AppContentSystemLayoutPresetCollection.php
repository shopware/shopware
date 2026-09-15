<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemLayoutPresetEntity>
 */
#[Package('framework')]
class AppContentSystemLayoutPresetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemLayoutPresetEntity::class;
    }
}
