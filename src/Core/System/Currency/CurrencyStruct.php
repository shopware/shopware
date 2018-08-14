<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class CurrencyStruct extends Entity
{
    /**
     * @var float
     */
    protected $factor;

    /**
     * @var string
     */
    protected $symbol;

    /**
     * @var string
     */
    protected $shortName;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $isDefault;

    /**
     * @var bool
     */
    protected $placedInFront;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CurrencyTranslationCollection|null
     */
    protected $translations;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannelDefaultAssignments;

    /**
     * @var ProductPriceRuleCollection|null
     */
    protected $productPriceRules;

    public function getFactor(): float
    {
        return $this->factor;
    }

    public function setFactor(float $factor): void
    {
        $this->factor = $factor;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getPlacedInFront(): bool
    {
        return $this->placedInFront;
    }

    public function setPlacedInFront(bool $placedInFront): void
    {
        $this->placedInFront = $placedInFront;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

    public function getTranslations(): ?CurrencyTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getSalesChannelDefaultAssignments(): ?SalesChannelCollection
    {
        return $this->salesChannelDefaultAssignments;
    }

    public function setSalesChannelDefaultAssignments(SalesChannelCollection $salesChannelDefaultAssignments): void
    {
        $this->salesChannelDefaultAssignments = $salesChannelDefaultAssignments;
    }

    public function getProductPriceRules(): ?ProductPriceRuleCollection
    {
        return $this->productPriceRules;
    }

    public function setProductPriceRules(ProductPriceRuleCollection $productPriceRules): void
    {
        $this->productPriceRules = $productPriceRules;
    }
}
