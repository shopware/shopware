<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @final LineItem class should not be extended because it is serialized into the storage and should not depend on individual implementations.
 */
#[Package('checkout')]
class LineItem extends Struct
{
    final public const CREDIT_LINE_ITEM_TYPE = 'credit';
    final public const PRODUCT_LINE_ITEM_TYPE = 'product';
    final public const CUSTOM_LINE_ITEM_TYPE = 'custom';
    final public const PROMOTION_LINE_ITEM_TYPE = 'promotion';
    final public const DISCOUNT_LINE_ITEM = 'discount';
    final public const CONTAINER_LINE_ITEM = 'container';

    /**
     * @var array<mixed>
     */
    protected array $payload = [];

    protected ?string $label = null;

    protected int $quantity;

    protected ?PriceDefinitionInterface $priceDefinition = null;

    protected ?CalculatedPrice $price = null;

    protected bool $good = true;

    protected ?string $description = null;

    protected ?MediaEntity $cover = null;

    protected ?DeliveryInformation $deliveryInformation = null;

    protected LineItemCollection $children;

    protected ?Rule $requirement = null;

    protected bool $removable = false;

    protected bool $stackable = false;

    protected ?QuantityInformation $quantityInformation = null;

    protected bool $modified = false;

    /**
     * The data timestamp can be used to record when the line item was last updated with data from the database.
     * Updating the data timestamp must be done by the corresponding cart data collector.
     */
    protected ?\DateTimeInterface $dataTimestamp = null;

    /**
     * Data data context hash can be used, like the data timestamp, to check if the line item was calculated with the same
     * context hash or not
     */
    protected ?string $dataContextHash = null;

    /**
     * Used as a unique id to identify a line item over multiple nested levels
     */
    protected string $uniqueIdentifier;

    /**
     * @var array<int, string>
     */
    protected array $states = [];

    protected bool $modifiedByApp = false;

    /**
     * @var array<string, bool>
     */
    private array $payloadProtection = [];

    /**
     * @throws CartException
     */
    public function __construct(
        protected string $id,
        protected string $type,
        protected ?string $referencedId = null,
        int $quantity = 1
    ) {
        $this->uniqueIdentifier = Uuid::randomHex();
        $this->children = new LineItemCollection();

        if ($quantity < 1) {
            throw CartException::invalidQuantity($quantity);
        }
        $this->quantity = $quantity;
    }

    /**
     * @throws CartException
     */
    public static function createFromLineItem(LineItem $lineItem): self
    {
        $self = new self($lineItem->id, $lineItem->type, $lineItem->getReferencedId(), $lineItem->quantity);

        foreach (get_object_vars($lineItem) as $property => $value) {
            $self->$property = $value; /* @phpstan-ignore-line */
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
     * @throws CartException
     */
    public function setQuantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw CartException::invalidQuantity($quantity);
        }

        if (!$this->isStackable()) {
            throw CartException::lineItemNotStackable($this->id);
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

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return mixed|null
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
     * @throws CartException
     */
    public function removePayloadValue(string $key): void
    {
        if (!$this->hasPayloadValue($key)) {
            throw CartException::payloadKeyNotFound($key, $this->getId());
        }
        unset($this->payload[$key]);
        unset($this->payloadProtection[$key]);
    }

    /**
     * @deprecated tag:v6.6.0 - reason:new-optional-parameter - Parameter $protected will be added in v6.6.0
     *
     * @param mixed|null $value
     *
     * @throws CartException
     */
    public function setPayloadValue(string $key, $value/*, ?bool $protected = null*/): self
    {
        $protected = false;
        if (\func_num_args() === 3) {
            $protected = func_get_arg(2);
        }

        if ($value !== null && !\is_scalar($value) && !\is_array($value)) {
            throw CartException::invalidPayload($key, $this->getId());
        }

        $this->payload[$key] = $value;

        if ($protected !== null) {
            $this->addPayloadProtection([$key => $protected]);
        }

        return $this;
    }

    /**
     * @deprecated tag:v6.6.0 - reason:new-optional-parameter - Parameter $protection will be added in v6.6.0
     *
     * @param array<string, mixed> $payload
     *
     * @throws CartException
     */
    public function setPayload(array $payload/*, array $protection = []*/): self
    {
        $protection = [];
        if (\func_num_args() === 2) {
            $protection = func_get_arg(1);
        }

        foreach ($payload as $key => $value) {
            if (\is_string($key)) {
                $this->setPayloadValue($key, $value);

                continue;
            }

            throw CartException::invalidPayload((string) $key, $this->getId());
        }

        return $this->addPayloadProtection($protection);
    }

    /**
     * @param array<string, bool> $protection
     */
    public function addPayloadProtection(array $protection): self
    {
        $this->payloadProtection = \array_replace($this->payloadProtection, $protection);

        return $this;
    }

    /**
     * @deprecated tag:v6.6.0 - reason:new-optional-parameter - Parameter $protection will be added in v6.6.0
     *
     * @param array<string, mixed> $payload
     */
    public function replacePayload(array $payload/*, array $protection = []*/): self
    {
        $protection = [];
        if (\func_num_args() === 2) {
            $protection = func_get_arg(1);
        }

        $this->payload = \array_replace_recursive($this->payload, $payload);

        return $this->addPayloadProtection($protection);
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
     * @throws CartException
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
     * @see LineItem::$dataTimestamp
     */
    public function getDataTimestamp(): ?\DateTimeInterface
    {
        return $this->dataTimestamp;
    }

    /**
     * @see LineItem::$dataTimestamp
     */
    public function setDataTimestamp(?\DateTimeInterface $dataTimestamp): void
    {
        $this->dataTimestamp = $dataTimestamp;
    }

    /**
     * @see LineItem::$dataContextHash
     */
    public function getDataContextHash(): ?string
    {
        return $this->dataContextHash;
    }

    /**
     * @see LineItem::$dataContextHash
     */
    public function setDataContextHash(?string $dataContextHash): void
    {
        $this->dataContextHash = $dataContextHash;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->uniqueIdentifier;
    }

    /**
     * @return array<int, string>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @param array<int, string> $states
     */
    public function setStates(array $states): LineItem
    {
        $this->states = $states;

        return $this;
    }

    public function hasState(string $state): bool
    {
        return \in_array($state, $this->states, true);
    }

    public function markUnModifiedByApp(): void
    {
        $this->modifiedByApp = false;
    }

    public function markModifiedByApp(): void
    {
        $this->modifiedByApp = true;
    }

    public function isModifiedByApp(): bool
    {
        return $this->modifiedByApp;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $payload = [];

        foreach ($data['payload'] as $key => $value) {
            if (isset($this->payloadProtection[$key]) && $this->payloadProtection[$key] === true) {
                continue;
            }
            $payload[$key] = $value;
        }
        $data['payload'] = $payload;

        return $data;
    }

    /**
     * @throws CartException
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
     * @throws CartException
     */
    private function validateChildQuantity(LineItem $child): void
    {
        $childQuantity = $child->getQuantity();
        $parentQuantity = $this->getQuantity();
        if ($childQuantity % $parentQuantity === 0) {
            return;
        }

        if ($childQuantity !== 1) {
            throw CartException::invalidChildQuantity($childQuantity, $parentQuantity);
        }

        // A quantity of 1 for a child line item is allowed, if the parent line item is not stackable
        if ($this->isStackable()) {
            throw CartException::invalidChildQuantity($childQuantity, $parentQuantity);
        }
    }
}
