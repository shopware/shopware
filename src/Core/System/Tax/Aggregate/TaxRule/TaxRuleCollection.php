<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxRuleEntity>
 */
#[Package('checkout')]
class TaxRuleCollection extends EntityCollection
{
    public function sortByTypePosition(): void
    {
        $this->sort(fn (TaxRuleEntity $entityA, TaxRuleEntity $entityB) => $entityA->getType()->getPosition() <=> $entityB->getType()->getPosition());
    }

    public function filterByTypePosition(int $position): TaxRuleCollection
    {
        return $this->filter(fn (TaxRuleEntity $taxRule) => $taxRule->getType()->getPosition() === $position);
    }

    public function highestTypePosition(): ?TaxRuleEntity
    {
        return $this->reduce(fn (?TaxRuleEntity $result, TaxRuleEntity $item) => $result === null || $item->getType()->getPosition() < $result->getType()->getPosition() ? $item : $result);
    }

    public function latestActivationDate(): ?TaxRuleEntity
    {
        return $this->reduce(fn (?TaxRuleEntity $result, TaxRuleEntity $item) => $result === null || $item->getActiveFrom() > $result->getActiveFrom() ? $item : $result);
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
