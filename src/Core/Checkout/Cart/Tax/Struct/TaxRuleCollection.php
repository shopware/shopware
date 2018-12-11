<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Collection;

class TaxRuleCollection extends Collection
{
    /**
     * @var TaxRule[]
     */
    protected $elements = [];

    public function add(TaxRule $taxRule): void
    {
        $this->elements[$this->getKey($taxRule)] = $taxRule;
    }

    public function remove(float $taxRate): void
    {
        parent::doRemoveByKey((string) $taxRate);
    }

    public function removeElement(TaxRule $taxRule): void
    {
        parent::doRemoveByKey($this->getKey($taxRule));
    }

    public function exists(TaxRule $taxRule): bool
    {
        return parent::has($this->getKey($taxRule));
    }

    public function get(float $taxRate): ?TaxRule
    {
        $key = (string) $taxRate;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function merge(self $rules): self
    {
        $new = new self($this->elements);

        $rules->map(
            function (TaxRule $rule) use ($new) {
                if (!$new->exists($rule)) {
                    $new->add($rule);
                }
            }
        );

        return $new;
    }

    protected function getKey(TaxRule $element): string
    {
        return (string) $element->getTaxRate();
    }
}
