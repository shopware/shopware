<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Script\Service\ArrayFunctions;
use Shopware\Core\Framework\Uuid\Uuid;

class ItemFacade
{
    protected LineItem $item;

    protected Services $services;

    /**
     * @internal
     */
    public function __construct(LineItem $item, Services $services)
    {
        $this->item = $item;
        $this->services = $services;
    }

    public function getPrice(): ?PriceFacade
    {
        if ($this->item->getPrice()) {
            return new PriceFacade($this->item->getPrice(), $this->services);
        }

        return null;
    }

    public function take(int $quantity, ?string $key = null): ?ItemFacade
    {
        if (!$this->item->isStackable()) {
            return null;
        }

        if ($quantity >= $this->item->getQuantity()) {
            return null;
        }

        $new = clone $this->item;
        $new->setId($key ?? Uuid::randomHex());
        $new->setQuantity($quantity);

        $this->item->setQuantity(
            $this->item->getQuantity() - $quantity
        );

        return new ItemFacade($new, $this->services);
    }

    /**
     * @param string|array|bool|int|float|null $value
     */
    public function payload(string $key, $value): void
    {
        $this->item->setPayloadValue($key, $value);
    }

    public function getId(): string
    {
        return $this->item->getId();
    }

    public function getReferencedId(): ?string
    {
        return $this->item->getReferencedId();
    }

    public function getQuantity(): int
    {
        return $this->item->getQuantity();
    }

    public function getLabel(): ?string
    {
        return $this->item->getLabel();
    }

    public function getPayload(): array
    {
        return $this->item->getPayload();
    }

    /**
     * @return string|array|bool|int|float|null
     */
    public function getPayloadValue(string $key)
    {
        return $this->item->getPayloadValue($key);
    }

    public function getProductNumber(): ?string
    {
        return $this->item->getPayloadValue('productNumber');
    }

    public function getManufacturerId(): ?string
    {
        return $this->item->getPayloadValue('manufacturerId');
    }

    public function getCustomFields(): array
    {
        $this->initPayloadArray('customFields');

        return $this->item->payload['customFields'];
    }

    public function tags(): ArrayFunctions
    {
        $this->initPayloadArray('tagIds');

        return new ArrayFunctions(
            $this->item->payload['tagIds']
        );
    }

    public function properties(): ArrayFunctions
    {
        $this->initPayloadArray('propertyIds');

        return new ArrayFunctions(
            $this->item->payload['propertyIds']
        );
    }

    public function categories(): ArrayFunctions
    {
        $this->initPayloadArray('categoryIds');

        return new ArrayFunctions(
            $this->item->payload['categoryIds']
        );
    }

    public function streams(): ArrayFunctions
    {
        $this->initPayloadArray('streamIds');

        return new ArrayFunctions(
            $this->item->payload['streamIds']
        );
    }

    public function getChildren(): ItemFunctions
    {
        return new ItemFunctions($this->item->getChildren(), $this->services);
    }

    public function getType(): string
    {
        return $this->item->getType();
    }

    /**
     * @internal
     */
    public function getItem(): LineItem
    {
        return $this->item;
    }

    private function initPayloadArray(string $key): void
    {
        if ($this->item->hasPayloadValue($key)) {
            return;
        }

        $this->item->setPayloadValue($key, []);
    }
}
