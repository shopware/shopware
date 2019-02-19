<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Collection;

class CalculatedTaxCollection extends Collection
{
    public function add($calculatedTax): void
    {
        $this->set($this->getKey($calculatedTax), $calculatedTax);
    }

    public function removeElement(CalculatedTax $calculatedTax): void
    {
        $this->remove($this->getKey($calculatedTax));
    }

    public function exists(CalculatedTax $calculatedTax): bool
    {
        return $this->has($this->getKey($calculatedTax));
    }

    public function get($key): ? CalculatedTax
    {
        $key = (string) $key;

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

            $taxRate = (string) $calculatedTax->getTaxRate();
            $new->get($taxRate)->increment($calculatedTax);
        }

        return $new;
    }

    protected function getExpectedClass(): ?string
    {
        return CalculatedTax::class;
    }

    protected function getKey(CalculatedTax $element): string
    {
        return (string) $element->getTaxRate();
    }
}
