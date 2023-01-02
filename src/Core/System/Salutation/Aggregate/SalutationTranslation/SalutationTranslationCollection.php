<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SalutationTranslationEntity>
 * @package customer-order
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
