<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LineItemQuantity>
 */
#[Package('checkout')]
class LineItemQuantityCollection extends Collection
{
    /**
     * @param string $key
     */
    public function has($key): bool
    {
        /** @var LineItemQuantity $element */
        foreach ($this->elements as $element) {
            if ($element->getLineItemId() === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * This function compresses all line items of the
     * same id and aggregates their quantity values.
     */
    public function compress(): void
    {
        $tmpItems = [];

        /** @var LineItemQuantity $element */
        foreach ($this->elements as $element) {
            if (!\array_key_exists($element->getLineItemId(), $tmpItems)) {
                $tmpItems[$element->getLineItemId()] = $element;
            } else {
                $existing = $tmpItems[$element->getLineItemId()];
                // update quantity
                $existing->setQuantity($existing->getQuantity() + $element->getQuantity());
                // set again
                $tmpItems[$element->getLineItemId()] = $existing;
            }
        }

        $this->elements = array_values($tmpItems);
    }

    public function getApiAlias(): string
    {
        return 'cart_line_item_quantity_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return LineItemQuantity::class;
    }
}
