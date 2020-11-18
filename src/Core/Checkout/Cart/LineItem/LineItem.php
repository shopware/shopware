<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Exception\InvalidChildQuantityException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;

class LineItem extends Struct
{
    public const CREDIT_LINE_ITEM_TYPE = 'credit';
    public const PRODUCT_LINE_ITEM_TYPE = 'product';
    public const CUSTOM_LINE_ITEM_TYPE = 'custom';
    public const PROMOTION_LINE_ITEM_TYPE = 'promotion';

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $referencedId;

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
     * @var bool
     */
    protected $modified = false;

    /**
     * @throws InvalidQuantityException
     */
    public function __construct(string $id, string $type, ?string $referencedId = null, int $quantity = 1)
    {
        $this->id = $id;
        $this->type = $type;
        $this->children = new LineItemCollection();

        if ($quantity < 1) {
            throw new InvalidQuantityException($quantity);
        }
        $this->referencedId = $referencedId;
        $this->quantity = $quantity;
    }

    /**
     * @throws InvalidQuantityException
     */
    public static function createFromLineItem(LineItem $lineItem): self
    {
        $self = new self($lineItem->id, $lineItem->type, $lineItem->getReferencedId(), $lineItem->quantity);

        foreach (get_object_vars($lineItem) as $property => $value) {
            $self->$property = $value;
        }

        return $self;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getReferencedId(): ?string
    {
        return $this->referencedId;
    }

    public function setReferencedId(?string $referencedId): self
    {
        $this->referencedId = $referencedId;

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
            throw new LineItemNotStackableException($this->id);
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
    public function getPayloadValue(string $key)
    {
        if (!$this->hasPayloadValue($key)) {
            return null;
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
            throw new PayloadKeyNotFoundException($key, $this->getId());
        }
        unset($this->payload[$key]);
    }

    /**
     * @param array|bool|float|int|string|null $value
     *
     * @throws InvalidPayloadException
     */
    public function setPayloadValue(string $key, $value): self
    {
        if ($value !== null && !is_scalar($value) && !\is_array($value)) {
            throw new InvalidPayloadException($key, $this->getId());
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
            if (!\is_string($key)) {
                throw new InvalidPayloadException((string) $key, $this->getId());
            }

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
     * @throws InvalidChildQuantityException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
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

    public function setQuantityInformation(?QuantityInformation $quantityInformation): LineItem
    {
        $this->quantityInformation = $quantityInformation;

        return $this;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function markModified(): void
    {
        $this->modified = true;
    }

    public function markUnmodified(): void
    {
        $this->modified = false;
    }

    public function getApiAlias(): string
    {
        return 'cart_line_item';
    }

    /**
     * @throws InvalidQuantityException
     */
    private function refreshChildQuantity(
        LineItemCollection $lineItems,
        int $oldParentQuantity,
        int $newParentQuantity
    ): void {
        foreach ($lineItems as $lineItem) {
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
        $childQuantity = $child->getQuantity();
        $parentQuantity = $this->getQuantity();
        if ($childQuantity % $parentQuantity !== 0) {
            if ($childQuantity !== 1) {
                throw new InvalidChildQuantityException($childQuantity, $parentQuantity);
            }

            // A quantity of 1 for a child line item is allowed, if the parent line item is not stackable
            if ($this->isStackable()) {
                throw new InvalidChildQuantityException($childQuantity, $parentQuantity);
            }
        }
    }
}
