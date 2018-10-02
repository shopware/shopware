<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Struct\Collection;

class LineItemCollection extends Collection
{
    /**
     * @var LineItem[]
     */
    protected $elements = [];

    public function add(LineItem $lineItem): void
    {
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

    public function remove(string $identifier): void
    {
        parent::doRemoveByKey($identifier);
    }

    public function removeElement(LineItem $lineItem): void
    {
        parent::doRemoveByKey($this->getKey($lineItem));
    }

    public function exists(LineItem $lineItem): bool
    {
        return parent::has($this->getKey($lineItem));
    }

    public function get(string $identifier): ? LineItem
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
        return $this->buildFlat($this->getElements());
    }

    public function current(): LineItem
    {
        return parent::current();
    }

    public function sortByPriority(): void
    {
        $this->sort(
            function (LineItem $a, LineItem $b) {
                return $b->getPriority() <=> $a->getPriority();
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

    private function buildFlat(array $lineItems): array
    {
        $flat = [];
        foreach ($lineItems as $lineItem) {
            $flat[] = $lineItem;
            if (!$lineItem->getChildren()) {
                continue;
            }

            $nested = $this->buildFlat($lineItem->getChildren()->getElements());

            foreach ($nested as $nest) {
                $flat[] = $nest;
            }
        }

        return $flat;
    }
}
