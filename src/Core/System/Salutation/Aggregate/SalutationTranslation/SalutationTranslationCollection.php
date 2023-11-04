<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalutationTranslationEntity>
 */
#[Package('customer-order')]
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
