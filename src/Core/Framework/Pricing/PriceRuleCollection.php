<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PriceRuleCollection extends EntityCollection
{
    /**
     * @var PriceRuleEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? PriceRuleEntity
    {
        return parent::get($id);
    }

    public function current(): PriceRuleEntity
    {
        return parent::current();
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (PriceRuleEntity $price) {
            return $price->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (PriceRuleEntity $price) use ($id) {
            return $price->getCurrencyId() === $id;
        });
    }

    public function getRuleIds(): array
    {
        return $this->fmap(function (PriceRuleEntity $price) {
            return $price->getRuleId();
        });
    }

    public function filterByRuleId(string $id): self
    {
        return $this->filter(function (PriceRuleEntity $priceRule) use ($id) {
            return $priceRule->getRuleId() === $id;
        });
    }

    public function getPriceRulesForContext(Context $context): ?self
    {
        foreach ($context->getRules() as $ruleId) {
            $rules = $this->filterByRuleId($ruleId);

            if ($rules->count() > 0) {
                return $rules;
            }
        }

        return null;
    }

    public function sortByPriceAscending(): void
    {
        $this->sort(function (PriceRuleEntity $a, PriceRuleEntity $b) {
            return $a->getPrice()->getGross() <=> $b->getPrice()->getGross();
        });
    }

    protected function getExpectedClass(): string
    {
        return PriceRuleEntity::class;
    }
}
