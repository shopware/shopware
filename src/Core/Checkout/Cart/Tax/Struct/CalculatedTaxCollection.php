<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Collection;

class CalculatedTaxCollection extends Collection
{
    /**
     * @var CalculatedTax[]
     */
    protected $elements = [];

    public function add(CalculatedTax $calculatedTax): void
    {
        $this->elements[$this->getKey($calculatedTax)] = $calculatedTax;
    }

    public function remove(float $taxRate): void
    {
        parent::doRemoveByKey((string) $taxRate);
    }

    public function removeElement(CalculatedTax $calculatedTax): void
    {
        parent::doRemoveByKey($this->getKey($calculatedTax));
    }

    public function exists(CalculatedTax $calculatedTax): bool
    {
        return parent::has($this->getKey($calculatedTax));
    }

    public function get(float $taxRate): ? CalculatedTax
    {
        $key = (string) $taxRate;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    /**
     * Returns the total calculated tax for this item
     *
     * @return float
     */
    public function getAmount(): float
    {
        $amounts = $this->map(
            function (CalculatedTax $calculatedTax) {
                return $calculatedTax->getTax();
            }
        );

        return array_sum($amounts);
    }

    public function merge(self $taxCollection): self
    {
        $new = new self($this->elements);

        /** @var CalculatedTax $calculatedTax */
        foreach ($taxCollection as $calculatedTax) {
            if (!$new->exists($calculatedTax)) {
                $new->add(clone $calculatedTax);
                continue;
            }

            $new->get($calculatedTax->getTaxRate())
                ->increment($calculatedTax);
        }

        return $new;
    }

    protected function getKey(CalculatedTax $element): string
    {
        return (string) $element->getTaxRate();
    }
}
