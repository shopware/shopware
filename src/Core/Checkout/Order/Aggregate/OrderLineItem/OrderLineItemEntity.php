<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class OrderLineItemEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string|null
     */
    protected $referencedId;

    /**
     * @var string|null
     */
    protected $productId;

    /**
     * @internal
     */
    protected ?string $promotionId = null;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var bool
     */
    protected $good;

    /**
     * @var bool
     */
    protected $removable;

    /**
     * @var string|null
     */
    protected $coverId;

    /**
     * @var bool
     */
    protected $stackable;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var CalculatedPrice|null
     */
    protected $price;

    /**
     * @var PriceDefinitionInterface|null
     */
    protected $priceDefinition;

    /**
     * @var array<string>|null
     */
    protected $payload;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var OrderLineItemEntity|null
     */
    protected $parent;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var OrderDeliveryPositionCollection|null
     */
    protected $orderDeliveryPositions;

    /**
     * @var MediaEntity|null
     */
    protected $cover;

    /**
     * @var OrderLineItemCollection|null
     *
     * @internal
     */
    protected $children;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    protected ?OrderTransactionCaptureRefundPositionCollection $orderTransactionCaptureRefundPositions = null;

    /**
     * @var array<int, string>
     */
    protected array $states = [];

    protected ?OrderLineItemDownloadCollection $downloads = null;

    protected ?PromotionEntity $promotion = null;

    /**
     * @var string
     */
    protected $orderVersionId;

    /**
     * @var string
     */
    protected $productVersionId;

    /**
     * @var string
     */
    protected $parentVersionId;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getReferencedId(): ?string
    {
        return $this->referencedId;
    }

    public function setReferencedId(?string $referencedId): void
    {
        $this->referencedId = $referencedId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getGood(): bool
    {
        return $this->good;
    }

    public function setGood(bool $good): void
    {
        $this->good = $good;
    }

    public function getRemovable(): bool
    {
        return $this->removable;
    }

    public function setRemovable(bool $removable): void
    {
        $this->removable = $removable;
    }

    public function getStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): void
    {
        $this->stackable = $stackable;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPrice(): ?CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(?CalculatedPrice $price): void
    {
        $this->price = $price;
    }

    public function getPriceDefinition(): ?PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinitionInterface $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParent(): ?OrderLineItemEntity
    {
        return $this->parent;
    }

    public function setParent(?OrderLineItemEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderDeliveryPositions(): ?OrderDeliveryPositionCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function setOrderDeliveryPositions(OrderDeliveryPositionCollection $orderDeliveryPositions): void
    {
        $this->orderDeliveryPositions = $orderDeliveryPositions;
    }

    public function getCoverId(): ?string
    {
        return $this->coverId;
    }

    public function setCoverId(?string $coverId): void
    {
        $this->coverId = $coverId;
    }

    public function getCover(): ?MediaEntity
    {
        return $this->cover;
    }

    public function setCover(?MediaEntity $cover): void
    {
        $this->cover = $cover;
    }

    public function getChildren(): ?OrderLineItemCollection
    {
        return $this->children;
    }

    public function setChildren(OrderLineItemCollection $children): void
    {
        $this->children = $children;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getOrderTransactionCaptureRefundPositions(): ?OrderTransactionCaptureRefundPositionCollection
    {
        return $this->orderTransactionCaptureRefundPositions;
    }

    public function setOrderTransactionCaptureRefundPositions(OrderTransactionCaptureRefundPositionCollection $orderTransactionCaptureRefundPositions): void
    {
        $this->orderTransactionCaptureRefundPositions = $orderTransactionCaptureRefundPositions;
    }

    public function getPromotionId(): ?string
    {
        return $this->promotionId;
    }

    public function setPromotionId(?string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(?PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
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
    public function setStates(array $states): void
    {
        $this->states = $states;
    }

    public function getDownloads(): ?OrderLineItemDownloadCollection
    {
        return $this->downloads;
    }

    public function setDownloads(OrderLineItemDownloadCollection $downloads): void
    {
        $this->downloads = $downloads;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getParentVersionId(): string
    {
        return $this->parentVersionId;
    }

    public function setParentVersionId(string $parentVersionId): void
    {
        $this->parentVersionId = $parentVersionId;
    }
}
