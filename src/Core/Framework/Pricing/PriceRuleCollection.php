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
        $currencyIds = [];

        /** @var PriceRuleEntity $price */
        foreach ($this->elements as $price) {
            foreach ($price->getPrice() as $currencyPrice) {
                $currencyIds[$currencyPrice->getCurrencyId()] = true;
            }
        }

        return array_keys($currencyIds);
    }

    public function filterByCurrencyId(string $id): array
    {
        $prices = [];

        /** @var PriceRuleEntity $price */
        foreach ($this->elements as $price) {
            foreach ($price->getPrice() as $currencyPrice) {
                if ($currencyPrice->getCurrencyId() === $id) {
                    $prices[] = $currencyPrice;
                }
            }
        }

        return $prices;
    }

    public function getRuleIds(): array
    {
        return $this->fmap(function (PriceRuleEntity $price) {
            return $price->getRuleId();
        });
    }

    /**
     * @return static
     */
    public function filterByRuleId(string $id)
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
