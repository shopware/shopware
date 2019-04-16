<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationCollection;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Tag\TagCollection;

class ShippingMethodEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var bool
     */
    protected $bindShippingfree;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var float|null
     */
    protected $shippingFree;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $comment;

    /**
     * @var string
     */
    protected $deliveryTimeId;

    /**
     * @var DeliveryTimeEntity
     */
    protected $deliveryTime;

    /**
     * @var ShippingMethodTranslationCollection|null
     */
    protected $translations;

    /**
     * @var OrderDeliveryCollection|null
     */
    protected $orderDeliveries;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannelDefaultAssignments;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var RuleEntity|null
     */
    protected $availabilityRule;

    /**
     * @var string|null
     */
    protected $availabilityRuleId;

    /**
     * @var ShippingMethodPriceCollection
     */
    protected $prices;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    public function __construct()
    {
        $this->prices = new ShippingMethodPriceCollection();
    }

    public function getBindShippingfree(): bool
    {
        return $this->bindShippingfree;
    }

    public function setBindShippingfree(bool $bindShippingfree): void
    {
        $this->bindShippingfree = $bindShippingfree;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getShippingFree(): ?float
    {
        return $this->shippingFree;
    }

    public function setShippingFree(?float $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getDeliveryTimeId(): string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getDeliveryTime(): DeliveryTimeEntity
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(DeliveryTimeEntity $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getTranslations(): ?ShippingMethodTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(?ShippingMethodTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrderDeliveries(): ?OrderDeliveryCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getSalesChannelDefaultAssignments(): ?SalesChannelCollection
    {
        return $this->salesChannelDefaultAssignments;
    }

    public function setSalesChannelDefaultAssignments(SalesChannelCollection $salesChannelDefaultAssignments): void
    {
        $this->salesChannelDefaultAssignments = $salesChannelDefaultAssignments;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getPrices(): ShippingMethodPriceCollection
    {
        return $this->prices;
    }

    public function setPrices(ShippingMethodPriceCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getAvailabilityRule(): ?RuleEntity
    {
        return $this->availabilityRule;
    }

    public function setAvailabilityRule(?RuleEntity $availabilityRule): void
    {
        $this->availabilityRule = $availabilityRule;
    }

    public function getAvailabilityRuleId(): ?string
    {
        return $this->availabilityRuleId;
    }

    public function setAvailabilityRuleId(string $availabilityRuleId): void
    {
        $this->availabilityRuleId = $availabilityRuleId;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }
}
