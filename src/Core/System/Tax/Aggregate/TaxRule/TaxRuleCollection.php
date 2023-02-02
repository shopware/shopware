<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<TaxRuleEntity>
 */
class TaxRuleCollection extends EntityCollection
{
    public function sortByTypePosition(): void
    {
        $this->sort(function (TaxRuleEntity $entityA, TaxRuleEntity $entityB) {
            return $entityA->getType()->getPosition() <=> $entityB->getType()->getPosition();
        });
    }

    public function getApiAlias(): string
    {
        return 'tax_rule_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxRuleEntity::class;
    }
}
