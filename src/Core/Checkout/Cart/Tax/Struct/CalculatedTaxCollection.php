<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Checkout\Cart\Price\PriceRoundingInterface;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method CalculatedTax[]    getIterator()
 * @method CalculatedTax[]    getElements()
 * @method CalculatedTax|null get(string $key)
 * @method CalculatedTax|null first()
 * @method CalculatedTax|null last()
 */
class CalculatedTaxCollection extends Collection
{
    /**
     * @param CalculatedTax $calculatedTax
     */
    public function add($calculatedTax): void
    {
        $this->set($this->getKey($calculatedTax), $calculatedTax);
    }

    /**
     * @param string|int    $key
     * @param CalculatedTax $calculatedTax
     */
    public function set($key, $calculatedTax): void
    {
        parent::set($this->getKey($calculatedTax), $calculatedTax);
    }

    public function removeElement(CalculatedTax $calculatedTax): void
    {
        $this->remove($this->getKey($calculatedTax));
    }

    public function exists(CalculatedTax $calculatedTax): bool
    {
        return $this->has($this->getKey($calculatedTax));
    }

    public function sortByTax(): CalculatedTaxCollection
    {
        $this->sort(function (CalculatedTax $a, CalculatedTax $b) {
            return $a->getTaxRate() <=> $b->getTaxRate();
        });

        return $this;
    }

    /**
     * Returns the total calculated tax for this item
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

        foreach ($taxCollection as $calculatedTax) {
            $exists = $new->get($this->getKey($calculatedTax));
            if (!$exists) {
                $new->add(clone $calculatedTax);

                continue;
            }

            $exists->increment($calculatedTax);
        }

        return $new;
    }

    public function round(PriceRoundingInterface $priceRounding, int $precision): void
    {
        foreach ($this->elements as $tax) {
            $tax->setTax(
                $priceRounding->round($tax->getTax(), $precision)
            );
        }
    }

    public function getApiAlias(): string
    {
        return 'cart_tax_calculated_collection';
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
