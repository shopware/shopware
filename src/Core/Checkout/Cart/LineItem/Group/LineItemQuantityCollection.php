<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method LineItemQuantity[]    getIterator()
 * @method LineItemQuantity[]    getElements()
 * @method LineItemQuantity|null first()
 * @method LineItemQuantity|null last()
 */
class LineItemQuantityCollection extends Collection
{
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
            if (!array_key_exists($element->getLineItemId(), $tmpItems)) {
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

    protected function getExpectedClass(): ?string
    {
        return LineItemQuantity::class;
    }
}
