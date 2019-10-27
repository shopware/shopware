<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(TaxAreaRuleEntity $entity)
 * @method void                   set(string $key, TaxAreaRuleEntity $entity)
 * @method TaxAreaRuleEntity[]    getIterator()
 * @method TaxAreaRuleEntity[]    getElements()
 * @method TaxAreaRuleEntity|null get(string $key)
 * @method TaxAreaRuleEntity|null first()
 * @method TaxAreaRuleEntity|null last()
 */
class TaxAreaRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaxAreaRuleEntity::class;
    }
}
