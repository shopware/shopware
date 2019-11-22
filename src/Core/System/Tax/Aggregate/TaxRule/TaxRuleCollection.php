<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(TaxRuleEntity $entity)
 * @method void               set(string $key, TaxRuleEntity $entity)
 * @method TaxRuleEntity[]    getIterator()
 * @method TaxRuleEntity[]    getElements()
 * @method TaxRuleEntity|null get(string $key)
 * @method TaxRuleEntity|null first()
 * @method TaxRuleEntity|null last()
 */
class TaxRuleCollection extends EntityCollection
{
    public function sortByTypePosition(): void
    {
        $this->sort(function (TaxRuleEntity $entityA, TaxRuleEntity $entityB) {
            return $entityA->getType()->getPosition() <=> $entityB->getType()->getPosition();
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxRuleEntity::class;
    }
}
