<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method LineItem[]    getIterator()
 * @method LineItem[]    getElements()
 * @method LineItem|null first()
 * @method LineItem|null last()
 */
class LineItemCollection extends Collection
{
    /**
     * @param LineItem $lineItem
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function add($lineItem): void
    {
        $this->validateType($lineItem);

        $exists = $this->get($lineItem->getKey());

        if ($exists && $exists->getType() !== $lineItem->getType()) {
            throw new MixedLineItemTypeException($lineItem->getKey(), $exists->getType());
        }

        if ($exists) {
            $exists->setQuantity($lineItem->getQuantity() + $exists->getQuantity());

            return;
        }

        $this->elements[$this->getKey($lineItem)] = $lineItem;
    }

    public function removeElement(LineItem $lineItem): void
    {
        $this->remove($this->getKey($lineItem));
    }

    public function exists(LineItem $lineItem): bool
    {
        return $this->has($this->getKey($lineItem));
    }

    public function get($identifier): ?LineItem
    {
        if ($this->has($identifier)) {
            return $this->elements[$identifier];
        }

        return null;
    }

    public function filterType(string $type): self
    {
        return $this->filter(
            function (LineItem $lineItem) use ($type) {
                return $lineItem->getType() === $type;
            }
        );
    }

    public function getPayload(): array
    {
        return $this->map(function (LineItem $lineItem) {
            return $lineItem->getPayload();
        });
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            $this->fmap(function (LineItem $lineItem) {
                return $lineItem->getPrice();
            })
        );
    }

    public function getFlat(): array
    {
        return $this->buildFlat($this);
    }

    public function sortByPriority(): void
    {
        $this->sort(
            function (LineItem $a, LineItem $b) {
                $result = $b->getPriority() <=> $a->getPriority();
                if ($result === 0 && $a->getPriceDefinition() !== null && $b->getPriceDefinition() !== null) {
                    $result = $b->getPriceDefinition()->getPriority() <=> $a->getPriceDefinition()->getPriority();
                }

                return $result;
            }
        );
    }

    public function filterGoods(): self
    {
        return $this->filter(
            function (LineItem $lineItem) {
                return $lineItem->isGood();
            }
        );
    }

    public function getTypes(): array
    {
        return $this->fmap(
            function (LineItem $lineItem) {
                return $lineItem->getType();
            }
        );
    }

    protected function getKey(LineItem $element): string
    {
        return $element->getKey();
    }

    protected function getExpectedClass(): ?string
    {
        return LineItem::class;
    }

    private function buildFlat(LineItemCollection $lineItems): array
    {
        $flat = [];
        foreach ($lineItems as $lineItem) {
            $flat[] = $lineItem;
            if (!$lineItem->getChildren()) {
                continue;
            }

            $nested = $this->buildFlat($lineItem->getChildren());

            foreach ($nested as $nest) {
                $flat[] = $nest;
            }
        }

        return $flat;
    }
}
