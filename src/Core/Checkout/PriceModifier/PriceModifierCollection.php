<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<PriceModifier>
 */
#[Package('checkout')]
class PriceModifierCollection extends Collection
{
    /**
     * Keyed by the modifier's own id, same convention as LineItemCollection.
     */
    public function add($element): void
    {
        $this->set($this->getKey($element), $element);
    }

    public function set($key, $element): void
    {
        $this->validateType($element);

        $resolvedKey = $this->getKey($element);

        if ($resolvedKey === null) {
            $this->elements[] = $element;

            return;
        }

        $this->elements[$resolvedKey] = $element;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_modifier_collection';
    }

    protected function getKey(PriceModifier $element): ?string
    {
        return $element->getId();
    }

    protected function getExpectedClass(): ?string
    {
        return PriceModifier::class;
    }
}
