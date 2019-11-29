<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                 add(PriceRuleEntity $entity)
 * @method void                 set(string $key, PriceRuleEntity $entity)
 * @method PriceRuleEntity[]    getIterator()
 * @method PriceRuleEntity[]    getElements()
 * @method PriceRuleEntity|null get(string $key)
 * @method PriceRuleEntity|null first()
 * @method PriceRuleEntity|null last()
 */
class PriceRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PriceRuleEntity::class;
    }
}
