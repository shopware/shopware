<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
abstract class AbstractSalutationsSorter
{
    abstract public function getDecorated(): AbstractSalutationsSorter;

    abstract public function sort(SalutationCollection $salutations): SalutationCollection;
}
