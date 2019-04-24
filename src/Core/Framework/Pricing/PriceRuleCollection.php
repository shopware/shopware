<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

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

    protected function getExpectedClass(): string
    {
        return PriceRuleEntity::class;
    }
}
