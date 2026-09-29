<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class OrderPriceModificationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $orderId;

    protected string $orderVersionId;

    protected string $label;

    protected ?string $description = null;

    protected float $price;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $priceDefinition = null;

    protected int $position = 0;

    protected ?string $type = null;

    protected ?string $referencedId = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $payload = null;

    protected ?TaxRuleCollection $taxRules = null;

    protected ?OrderEntity $order = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
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

    /**
     * Signed: negative reduces the order total, positive surcharges it. For a `priceDefinition` of
     * type "percentage", this is a cache of what it last computed to, not the authority — see
     * getPriceDefinition().
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPriceDefinition(): ?array
    {
        return $this->priceDefinition;
    }

    /**
     * @param array<string, mixed>|null $priceDefinition
     */
    public function setPriceDefinition(?array $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function isTaxExempt(): bool
    {
        return $this->taxRules === null;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getReferencedId(): ?string
    {
        return $this->referencedId;
    }

    public function setReferencedId(?string $referencedId): void
    {
        $this->referencedId = $referencedId;
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

    public function getTaxRules(): ?TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function setTaxRules(?TaxRuleCollection $taxRules): void
    {
        $this->taxRules = $taxRules;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }
}
