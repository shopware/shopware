<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\ContextPriceStruct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Collection;

class ContextPriceCollection extends Collection
{
    /**
     * @var ContextPriceStruct[]
     */
    protected $elements = [];

    public function add(ContextPriceStruct $contextPrice): void
    {
        $this->elements[] = $contextPrice;
    }

    public function get(string $key): ? ContextPriceStruct
    {
        return $this->elements[$key];
    }

    public function current(): ContextPriceStruct
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
        $this->sort(function (ContextPriceStruct $a, ContextPriceStruct $b) {
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

    public function getPriceRulesForContext(ShopContext $context): ?self
    {
        foreach ($context->getContextRules() as $ruleId) {
            $rules = $this->filter(
                function (ContextPriceStruct $price) use ($ruleId) {
                    return $price->getRuleId() === $ruleId;
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
