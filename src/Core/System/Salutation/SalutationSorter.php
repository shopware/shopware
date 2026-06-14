<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class SalutationSorter extends AbstractSalutationsSorter
{
    public function getDecorated(): AbstractSalutationsSorter
    {
        throw new DecorationPatternException(self::class);
    }

    public function sort(SalutationCollection $salutations): SalutationCollection
    {
        $salutations->sortByPosition();

        return $salutations;
    }
}
