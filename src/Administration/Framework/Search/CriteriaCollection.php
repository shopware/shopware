<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void          add(Criteria $criteria)
 * @method void          set(string $key, Criteria $criteria)
 * @method Criteria[]    getIterator()
 * @method Criteria[]    getElements()
 * @method Criteria|null get(string $key)
 * @method Criteria|null first()
 * @method Criteria|null last()
 */
class CriteriaCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Criteria::class;
    }
}
