<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalutationEntity>
 */
#[Package('checkout')]
class SalutationCollection extends EntityCollection
{
    public function sortByPosition(): void
    {
        uasort($this->elements, static function (SalutationEntity $a, SalutationEntity $b): int {
            $aPosition = $a->getPosition();
            $bPosition = $b->getPosition();

            if ($aPosition === $bPosition) {
                return $a->getSalutationKey() <=> $b->getSalutationKey();
            }

            if ($aPosition === null) {
                return 1;
            }

            if ($bPosition === null) {
                return -1;
            }

            return $aPosition <=> $bPosition;
        });
    }

    public function getApiAlias(): string
    {
        return 'salutation_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalutationEntity::class;
    }
}
