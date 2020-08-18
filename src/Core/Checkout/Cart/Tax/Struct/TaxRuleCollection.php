<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method TaxRule[]    getIterator()
 * @method TaxRule[]    getElements()
 * @method TaxRule|null first()
 * @method TaxRule|null last()
 */
class TaxRuleCollection extends Collection
{
    /**
     * @param TaxRule $taxRule
     */
    public function add($taxRule): void
    {
        $this->set($this->getKey($taxRule), $taxRule);
    }

    /**
     * @param string|int $key
     * @param TaxRule    $taxRule
     */
    public function set($key, $taxRule): void
    {
        parent::set($this->getKey($taxRule), $taxRule);
    }

    public function removeElement(TaxRule $taxRule): void
    {
        $this->remove($this->getKey($taxRule));
    }

    public function exists(TaxRule $taxRule): bool
    {
        return $this->has($this->getKey($taxRule));
    }

    public function get($taxRate): ?TaxRule
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
            function (TaxRule $rule) use ($new): void {
                if (!$new->exists($rule)) {
                    $new->add($rule);
                }
            }
        );

        return $new;
    }

    public function getApiAlias(): string
    {
        return 'cart_tax_rule_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return TaxRule::class;
    }

    protected function getKey(TaxRule $element): string
    {
        return (string) $element->getTaxRate();
    }
}
