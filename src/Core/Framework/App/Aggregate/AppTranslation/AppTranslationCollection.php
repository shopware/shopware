<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(AppTranslationEntity $entity)
 * @method void                             set(string $key, AppTranslationEntity $entity)
 * @method \Generator<AppTranslationEntity> getIterator()
 * @method array<AppTranslationEntity>      getElements()
 * @method AppTranslationEntity|null        get(string $key)
 * @method AppTranslationEntity|null        first()
 * @method AppTranslationEntity|null        last()
 */
class AppTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppTranslationEntity::class;
    }
}
