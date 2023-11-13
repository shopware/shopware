<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LineItem>
 */
#[Package('checkout')]
class LineItemCollection extends Collection
{
    /**
     * @param LineItem[] $elements
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        foreach ($elements as $lineItem) {
            $this->add($lineItem);
        }
    }

    /**
     * @param LineItem $lineItem
     *
     * @throws CartException
     */
    public function add($lineItem): void
    {
        $this->validateType($lineItem);

        $exists = $this->get($lineItem->getId());

        if ($exists && $exists->getType() !== $lineItem->getType()) {
            throw CartException::mixedLineItemType($lineItem->getId(), $lineItem->getType());
        }

        if ($exists) {
            $exists->setQuantity($lineItem->getQuantity() + $exists->getQuantity());

            return;
        }

        $this->elements[$this->getKey($lineItem)] = $lineItem;
    }

    /**
     * @param int|string $key
     * @param LineItem   $lineItem
     */
    public function set($key, $lineItem): void
    {
        $this->validateType($lineItem);

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

    /**
     * @return LineItem[]
     */
    public function filterFlatByType(string $type): array
    {
        $lineItems = $this->getFlat();

        $filtered = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === $type) {
                $filtered[] = $lineItem;
            }
        }

        return $filtered;
    }

    public function filterType(string $type): LineItemCollection
    {
        return $this->filter(
            fn (LineItem $lineItem) => $lineItem->getType() === $type
        );
    }

    public function hasLineItemWithState(string $state): bool
    {
        foreach ($this->buildFlat($this) as $lineItem) {
            if (\in_array($state, $lineItem->getStates(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getPayload(): array
    {
        return $this->map(fn (LineItem $lineItem) => $lineItem->getPayload());
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            \array_filter(array_map(static fn (LineItem $lineItem) => $lineItem->getPrice(), array_values($this->getElements())))
        );
    }

    /**
     * @return LineItem[]
     */
    public function getFlat(): array
    {
        return $this->buildFlat($this);
    }

    public function sortByPriority(): void
    {
        $lineItemsByPricePriority = [];
        /** @var LineItem $lineItem */
        foreach ($this->elements as $lineItem) {
            $priceDefinitionPriority = QuantityPriceDefinition::SORTING_PRIORITY;
            if ($lineItem->getPriceDefinition()) {
                $priceDefinitionPriority = $lineItem->getPriceDefinition()->getPriority();
            }

            if (!\array_key_exists($priceDefinitionPriority, $lineItemsByPricePriority)) {
                $lineItemsByPricePriority[$priceDefinitionPriority] = [];
            }
            $lineItemsByPricePriority[$priceDefinitionPriority][] = $lineItem;
        }

        // Sort all line items by their price definition priority
        krsort($lineItemsByPricePriority);

        if (\count($lineItemsByPricePriority)) {
            $this->elements = array_merge(...$lineItemsByPricePriority);
        }
    }

    public function filterGoods(): self
    {
        return $this->filter(
            fn (LineItem $lineItem) => $lineItem->isGood()
        );
    }

    /**
     * @return LineItem[]
     */
    public function filterGoodsFlat(): array
    {
        $lineItems = $this->getFlat();

        $filtered = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->isGood()) {
                $filtered[] = $lineItem;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string>
     */
    public function getTypes(): array
    {
        return $this->fmap(
            fn (LineItem $lineItem) => $lineItem->getType()
        );
    }

    /**
     * @return array<string|null>
     */
    public function getReferenceIds(): array
    {
        return $this->fmap(
            fn (LineItem $lineItem) => $lineItem->getReferencedId()
        );
    }

    public function getApiAlias(): string
    {
        return 'cart_line_item_collection';
    }

    public function getTotalQuantity(): int
    {
        return $this->reduce(fn ($result, $item) => $result + $item->getQuantity(), 0);
    }

    protected function getKey(LineItem $element): string
    {
        return $element->getId();
    }

    protected function getExpectedClass(): ?string
    {
        return LineItem::class;
    }

    /**
     * @return array<mixed>
     */
    private function buildFlat(LineItemCollection $lineItems): array
    {
        $flat = [];
        foreach ($lineItems as $lineItem) {
            $flat[] = $lineItem;

            foreach ($this->buildFlat($lineItem->getChildren()) as $nest) {
                $flat[] = $nest;
            }
        }

        return $flat;
    }
}
