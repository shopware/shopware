<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('inventory')]
class CurrencyEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $isoCode;

    /**
     * @var float
     */
    protected $factor;

    /**
     * @var string
     */
    protected $symbol;

    /**
     * @var string|null
     */
    protected $shortName;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

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
     * @var SalesChannelDomainCollection|null
     */
    protected $salesChannelDomains;

    /**
     * @var PromotionDiscountPriceCollection
     */
    protected $promotionDiscountPrices;

    /**
     * @var bool|null
     */
    protected $isSystemDefault;

    /**
     * @var ProductExportCollection|null
     */
    protected $productExports;

    /**
     * @var CurrencyCountryRoundingCollection|null
     */
    protected $countryRoundings;

    /**
     * @var CashRoundingConfig
     */
    protected $itemRounding;

    /**
     * @var CashRoundingConfig
     */
    protected $totalRounding;

    /**
     * @var float|null
     */
    protected $taxFreeFrom;

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }

    public function setIsoCode(string $isoCode): void
    {
        $this->isoCode = $isoCode;
    }

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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

    public function getSalesChannelDomains(): ?SalesChannelDomainCollection
    {
        return $this->salesChannelDomains;
    }

    public function setSalesChannelDomains(SalesChannelDomainCollection $salesChannelDomains): void
    {
        $this->salesChannelDomains = $salesChannelDomains;
    }

    public function getIsSystemDefault(): ?bool
    {
        return $this->isSystemDefault;
    }

    public function setIsSystemDefault(bool $isSystemDefault): void
    {
        $this->isSystemDefault = $isSystemDefault;
    }

    public function getPromotionDiscountPrices(): ?PromotionDiscountPriceCollection
    {
        return $this->promotionDiscountPrices;
    }

    public function setPromotionDiscountPrices(PromotionDiscountPriceCollection $promotionDiscountPrices): void
    {
        $this->promotionDiscountPrices = $promotionDiscountPrices;
    }

    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function getCountryRoundings(): ?CurrencyCountryRoundingCollection
    {
        return $this->countryRoundings;
    }

    public function setCountryRoundings(CurrencyCountryRoundingCollection $countryRoundings): void
    {
        $this->countryRoundings = $countryRoundings;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function setTotalRounding(CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getTaxFreeFrom(): ?float
    {
        return $this->taxFreeFrom;
    }

    public function setTaxFreeFrom(?float $taxFreeFrom): void
    {
        $this->taxFreeFrom = $taxFreeFrom;
    }
}
