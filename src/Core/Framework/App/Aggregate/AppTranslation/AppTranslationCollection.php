<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
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
