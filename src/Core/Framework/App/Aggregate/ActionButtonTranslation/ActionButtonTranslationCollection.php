<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<ActionButtonTranslationEntity>
 */
class ActionButtonTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }
}
