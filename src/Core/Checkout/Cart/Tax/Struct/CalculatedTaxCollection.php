<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Util\FloatComparator;

/**
 * @extends Collection<CalculatedTax>
 */
#[Package('checkout')]
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
        $this->sort(fn (CalculatedTax $a, CalculatedTax $b) => $a->getTaxRate() <=> $b->getTaxRate());

        return $this;
    }

    /**
     * Returns the total calculated tax for this item
     */
    public function getAmount(): float
    {
        $amounts = $this->map(
            fn (CalculatedTax $calculatedTax) => $calculatedTax->getTax()
        );

        return FloatComparator::cast(array_sum($amounts));
    }

    public function merge(self $taxCollection): self
    {
        $new = $this;

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

    public function round(CashRounding $rounding, CashRoundingConfig $config): void
    {
        foreach ($this->elements as $tax) {
            $tax->setTax(
                $rounding->mathRound($tax->getTax(), $config)
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
