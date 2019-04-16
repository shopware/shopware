<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Exception\InvalidChildQuantityException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Struct\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;

class LineItem extends Struct
{
    public const GOODS_PRIORITY = 100;

    public const VOUCHER_PRIORITY = 50;

    public const DISCOUNT_PRIORITY = 25;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $payload = [];

    /**
     * @var PriceDefinitionInterface|null
     */
    protected $priceDefinition;

    /**
     * @var CalculatedPrice|null
     */
    protected $price;

    /**
     * @var bool
     */
    protected $good = true;

    /**
     * @var int
     */
    protected $priority = self::GOODS_PRIORITY;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var MediaEntity|null
     */
    protected $cover;

    /**
     * @var DeliveryInformation|null
     */
    protected $deliveryInformation;

    /**
     * @var LineItemCollection
     */
    protected $children;

    /**
     * @var Rule|null
     */
    protected $requirement;

    /**
     * @var bool
     */
    protected $removable = false;

    /**
     * @var bool
     */
    protected $stackable = false;

    /**
     * @var QuantityInformation|null
     */
    protected $quantityInformation;

    /**
     * @throws InvalidQuantityException
     */
    public function __construct(string $key, string $type, int $quantity = 1, int $priority = self::GOODS_PRIORITY)
    {
        $this->key = $key;
        $this->type = $type;
        $this->priority = $priority;
        $this->children = new LineItemCollection();

        if ($quantity < 1) {
            throw new InvalidQuantityException($quantity);
        }
        $this->quantity = $quantity;
    }

    /**
     * @throws InvalidQuantityException
     */
    public static function createFromLineItem(LineItem $lineItem): self
    {
        $self = new static($lineItem->key, $lineItem->type, $lineItem->quantity, $lineItem->priority);

        $vars = get_object_vars($lineItem);
        foreach ($vars as $property => $value) {
            $self->$property = $value;
        }

        return $self;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function setQuantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidQuantityException($quantity);
        }

        if (!$this->isStackable()) {
            throw new LineItemNotStackableException($this->key);
        }

        if ($this->hasChildren()) {
            $this->refreshChildQuantity($this->children, $this->quantity, $quantity);
        }

        if ($this->priceDefinition instanceof QuantityPriceDefinition) {
            $this->price = null;
        }

        $this->quantity = $quantity;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function getPayloadValue(string $key): string
    {
        if (!$this->hasPayloadValue($key)) {
            throw new PayloadKeyNotFoundException($key, $this->getKey());
        }

        return $this->payload[$key];
    }

    public function hasPayloadValue(string $key): bool
    {
        return isset($this->payload[$key]);
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function removePayloadValue(string $key): void
    {
        if (!$this->hasPayloadValue($key)) {
            throw new PayloadKeyNotFoundException($key, $this->getKey());
        }
        unset($this->payload[$key]);
    }

    /**
     * @throws InvalidPayloadException
     */
    public function setPayloadValue(string $key, $value): self
    {
        if (!is_string($key) || ($value !== null && !is_scalar($value) && !is_array($value))) {
            throw new InvalidPayloadException($key, $this->getKey());
        }

        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * @throws InvalidPayloadException
     */
    public function setPayload(array $payload): self
    {
        foreach ($payload as $key => $value) {
            $this->setPayloadValue($key, $value);
        }

        return $this;
    }

    public function replacePayload(array $payload): self
    {
        $this->payload = array_replace_recursive($this->payload, $payload);

        return $this;
    }

    public function getPriceDefinition(): ?PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinitionInterface $priceDefinition): self
    {
        $this->priceDefinition = $priceDefinition;

        return $this;
    }

    public function getPrice(): ?CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(?CalculatedPrice $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function isGood(): bool
    {
        return $this->good;
    }

    public function setGood(bool $good): self
    {
        $this->good = $good;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCover(): ?MediaEntity
    {
        return $this->cover;
    }

    public function setCover(?MediaEntity $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getDeliveryInformation(): ?DeliveryInformation
    {
        return $this->deliveryInformation;
    }

    public function setDeliveryInformation(?DeliveryInformation $deliveryInformation): self
    {
        $this->deliveryInformation = $deliveryInformation;

        return $this;
    }

    public function getChildren(): LineItemCollection
    {
        return $this->children;
    }

    /**
     * @throws InvalidChildQuantityException
     */
    public function setChildren(LineItemCollection $children): self
    {
        foreach ($children as $child) {
            $this->validateChildQuantity($child);
        }
        $this->children = $children;

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->children->count() > 0;
    }

    /**
     * @throws MixedLineItemTypeException
     * @throws InvalidChildQuantityException
     */
    public function addChild(LineItem $child): self
    {
        $this->validateChildQuantity($child);
        $this->children->add($child);

        return $this;
    }

    public function setRequirement(?Rule $requirement): LineItem
    {
        $this->requirement = $requirement;

        return $this;
    }

    public function getRequirement(): ?Rule
    {
        return $this->requirement;
    }

    public function isRemovable(): bool
    {
        return $this->removable;
    }

    public function setRemovable(bool $removable): LineItem
    {
        $this->removable = $removable;

        return $this;
    }

    public function isStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): LineItem
    {
        $this->stackable = $stackable;

        return $this;
    }

    public function getQuantityInformation(): ?QuantityInformation
    {
        return $this->quantityInformation;
    }

    public function setQuantityInformation(QuantityInformation $quantityInformation): LineItem
    {
        $this->quantityInformation = $quantityInformation;

        return $this;
    }

    /**
     * @throws InvalidQuantityException
     */
    private function refreshChildQuantity(?LineItemCollection $lineItems, int $oldParentQuantity, int $newParentQuantity): void
    {
        foreach ($lineItems as $lineItem) {
            if (!$lineItem->isStackable()) {
                continue;
            }

            $newQuantity = intdiv($lineItem->getQuantity(), $oldParentQuantity) * $newParentQuantity;

            if ($lineItem->hasChildren()) {
                $this->refreshChildQuantity($lineItem->getChildren(), $lineItem->getQuantity(), $newQuantity);
            }

            $lineItem->quantity = $newQuantity;
            $priceDefinition = $lineItem->getPriceDefinition();
            if ($priceDefinition && $priceDefinition instanceof QuantityPriceDefinition) {
                $priceDefinition->setQuantity($lineItem->getQuantity());
            }
        }
    }

    /**
     * @throws InvalidChildQuantityException
     */
    private function validateChildQuantity(LineItem $child): void
    {
        if ($child->getQuantity() % $this->getQuantity() !== 0) {
            throw new InvalidChildQuantityException($child->getQuantity(), $this->getQuantity());
        }
    }
}
