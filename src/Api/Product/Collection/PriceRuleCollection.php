<?php

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\PriceRuleStruct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Collection;

class PriceRuleCollection extends Collection
{
    /**
     * @var PriceRuleStruct[]
     */
    protected $elements = [];

    public function add(PriceRuleStruct $priceRule): void
    {
        $this->elements[] = $priceRule;
    }

    public function get(string $key): ? PriceRuleStruct
    {
        return $this->elements[$key];
    }

    public function current(): PriceRuleStruct
    {
        return parent::current();
    }

    public function toArray()
    {
        $data = json_decode(json_encode($this), true);
        return $data['elements'];
    }

    public function sortByQuantity()
    {
        $this->sort(function(PriceRuleStruct $a, PriceRuleStruct $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrice(int $quantity)
    {
        foreach ($this->elements as $price) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            if ($price->getQuantityStart() <= $quantity && $end >= $quantity) {
                return $price;
            }
        }

        throw new \RuntimeException(sprintf('Price for quantity %s not found', $quantity));
    }

    public function getPriceRulesForContext(ShopContext $context): ?PriceRuleCollection
    {
        foreach ($context->getContextRules() as $ruleId) {
            $rules = $this->filter(
                function(PriceRuleStruct $rule) use ($ruleId) {
                    return $rule->getRuleId() === $ruleId;
                }
            );

            if ($rules->count() > 0) {
                $rules->sortByQuantity();
                return $rules;
            }
        }
        return null;
    }

}