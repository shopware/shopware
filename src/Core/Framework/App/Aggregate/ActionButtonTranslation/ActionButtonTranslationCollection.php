<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @method void                                 add(ActionButtonTranslationEntity $entity)
 * @method void                                 set(string $key, ActionButtonTranslationEntity $entity)
 * @method \Generator<AppTranslationEntity>     getIterator()
 * @method array<ActionButtonTranslationEntity> getElements()
 * @method ActionButtonTranslationEntity|null   get(string $key)
 * @method ActionButtonTranslationEntity|null   first()
 * @method ActionButtonTranslationEntity|null   last()
 */
class ActionButtonTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }
}
