<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<ActionButtonTranslationEntity>
 */
#[Package('core')]
class ActionButtonTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }
}
