<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class ShippingMethodEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var int
     */
    protected $type;

    /**
     * @var bool
     */
    protected $bindShippingfree;

    /**
     * @var bool
     */
    protected $bindLaststock;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $calculation;

    /**
     * @var int|null
     */
    protected $surchargeCalculation;

    /**
     * @var int
     */
    protected $taxCalculation;

    /**
     * @var float|null
     */
    protected $shippingFree;

    /**
     * @var int|null
     */
    protected $bindTimeFrom;

    /**
     * @var int|null
     */
    protected $bindTimeTo;

    /**
     * @var bool|null
     */
    protected $bindInstock;

    /**
     * @var int|null
     */
    protected $bindWeekdayFrom;

    /**
     * @var int|null
     */
    protected $bindWeekdayTo;

    /**
     * @var float|null
     */
    protected $bindWeightFrom;

    /**
     * @var float|null
     */
    protected $bindWeightTo;

    /**
     * @var float|null
     */
    protected $bindPriceFrom;

    /**
     * @var float|null
     */
    protected $bindPriceTo;

    /**
     * @var string|null
     */
    protected $bindSql;

    /**
     * @var string|null
     */
    protected $statusLink;

    /**
     * @var string|null
     */
    protected $calculationSql;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
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
     * @var ShippingMethodPriceCollection
     */
    protected $prices;

    /**
     * @var int
     */
    protected $minDeliveryTime;

    /**
     * @var int
     */
    protected $maxDeliveryTime;

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

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getBindShippingfree(): bool
    {
        return $this->bindShippingfree;
    }

    public function setBindShippingfree(bool $bindShippingfree): void
    {
        $this->bindShippingfree = $bindShippingfree;
    }

    public function getBindLaststock(): bool
    {
        return $this->bindLaststock;
    }

    public function setBindLaststock(bool $bindLaststock): void
    {
        $this->bindLaststock = $bindLaststock;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCalculation(): int
    {
        return $this->calculation;
    }

    public function setCalculation(int $calculation): void
    {
        $this->calculation = $calculation;
    }

    public function getSurchargeCalculation(): ?int
    {
        return $this->surchargeCalculation;
    }

    public function setSurchargeCalculation(?int $surchargeCalculation): void
    {
        $this->surchargeCalculation = $surchargeCalculation;
    }

    public function getTaxCalculation(): int
    {
        return $this->taxCalculation;
    }

    public function setTaxCalculation(int $taxCalculation): void
    {
        $this->taxCalculation = $taxCalculation;
    }

    public function getShippingFree(): ?float
    {
        return $this->shippingFree;
    }

    public function setShippingFree(?float $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getBindTimeFrom(): ?int
    {
        return $this->bindTimeFrom;
    }

    public function setBindTimeFrom(?int $bindTimeFrom): void
    {
        $this->bindTimeFrom = $bindTimeFrom;
    }

    public function getBindTimeTo(): ?int
    {
        return $this->bindTimeTo;
    }

    public function setBindTimeTo(?int $bindTimeTo): void
    {
        $this->bindTimeTo = $bindTimeTo;
    }

    public function getBindInstock(): ?bool
    {
        return $this->bindInstock;
    }

    public function setBindInstock(?bool $bindInstock): void
    {
        $this->bindInstock = $bindInstock;
    }

    public function getBindWeekdayFrom(): ?int
    {
        return $this->bindWeekdayFrom;
    }

    public function setBindWeekdayFrom(?int $bindWeekdayFrom): void
    {
        $this->bindWeekdayFrom = $bindWeekdayFrom;
    }

    public function getBindWeekdayTo(): ?int
    {
        return $this->bindWeekdayTo;
    }

    public function setBindWeekdayTo(?int $bindWeekdayTo): void
    {
        $this->bindWeekdayTo = $bindWeekdayTo;
    }

    public function getBindWeightFrom(): ?float
    {
        return $this->bindWeightFrom;
    }

    public function setBindWeightFrom(?float $bindWeightFrom): void
    {
        $this->bindWeightFrom = $bindWeightFrom;
    }

    public function getBindWeightTo(): ?float
    {
        return $this->bindWeightTo;
    }

    public function setBindWeightTo(?float $bindWeightTo): void
    {
        $this->bindWeightTo = $bindWeightTo;
    }

    public function getBindPriceFrom(): ?float
    {
        return $this->bindPriceFrom;
    }

    public function setBindPriceFrom(?float $bindPriceFrom): void
    {
        $this->bindPriceFrom = $bindPriceFrom;
    }

    public function getBindPriceTo(): ?float
    {
        return $this->bindPriceTo;
    }

    public function setBindPriceTo(?float $bindPriceTo): void
    {
        $this->bindPriceTo = $bindPriceTo;
    }

    public function getBindSql(): ?string
    {
        return $this->bindSql;
    }

    public function setBindSql(?string $bindSql): void
    {
        $this->bindSql = $bindSql;
    }

    public function getStatusLink(): ?string
    {
        return $this->statusLink;
    }

    public function setStatusLink(?string $statusLink): void
    {
        $this->statusLink = $statusLink;
    }

    public function getCalculationSql(): ?string
    {
        return $this->calculationSql;
    }

    public function setCalculationSql(?string $calculationSql): void
    {
        $this->calculationSql = $calculationSql;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
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

    public function getPrices(): ShippingMethodPriceCollection
    {
        return $this->prices;
    }

    public function setPrices(ShippingMethodPriceCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getMinDeliveryTime(): int
    {
        return $this->minDeliveryTime;
    }

    public function setMinDeliveryTime(int $minDeliveryTime): void
    {
        $this->minDeliveryTime = $minDeliveryTime;
    }

    public function getMaxDeliveryTime(): int
    {
        return $this->maxDeliveryTime;
    }

    public function setMaxDeliveryTime(int $maxDeliveryTime): void
    {
        $this->maxDeliveryTime = $maxDeliveryTime;
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
}
