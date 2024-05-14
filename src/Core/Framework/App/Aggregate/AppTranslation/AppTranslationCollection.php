<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppTranslationEntity>
 */
#[Package('core')]
class AppTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppTranslationEntity::class;
    }
}
