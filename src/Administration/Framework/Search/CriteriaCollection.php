<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Criteria>
 */
#[Package('administration')]
class CriteriaCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Criteria::class;
    }
}
