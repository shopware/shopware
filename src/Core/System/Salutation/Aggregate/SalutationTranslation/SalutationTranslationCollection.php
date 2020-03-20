<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(SalutationTranslationEntity $entity)
 * @method void                             set(string $key, SalutationTranslationEntity $entity)
 * @method SalutationTranslationEntity[]    getIterator()
 * @method SalutationTranslationEntity[]    getElements()
 * @method SalutationTranslationEntity|null get(string $key)
 * @method SalutationTranslationEntity|null first()
 * @method SalutationTranslationEntity|null last()
 */
class SalutationTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'salutation_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalutationTranslationEntity::class;
    }
}
