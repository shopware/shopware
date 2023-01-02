<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

#[Package('sales-channel')]
class SalesChannelDomainEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string|null
     */
    protected $currencyId;

    /**
     * @var CurrencyEntity|null
     */
    protected $currency;

    /**
     * @var string|null
     */
    protected $snippetSetId;

    /**
     * @var SnippetSetEntity|null
     */
    protected $snippetSet;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var ProductExportCollection|null
     */
    protected $productExports;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannelDefaultHreflang;

    /**
     * @var bool
     */
    protected $hreflangUseOnlyLocale;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getSnippetSetId(): ?string
    {
        return $this->snippetSetId;
    }

    public function setSnippetSetId(?string $snippetSetId): void
    {
        $this->snippetSetId = $snippetSetId;
    }

    public function getSnippetSet(): ?SnippetSetEntity
    {
        return $this->snippetSet;
    }

    public function setSnippetSet(?SnippetSetEntity $snippetSet): void
    {
        $this->snippetSet = $snippetSet;
    }

    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function isHreflangUseOnlyLocale(): bool
    {
        return $this->hreflangUseOnlyLocale;
    }

    public function setHreflangUseOnlyLocale(bool $hreflangUseOnlyLocale): void
    {
        $this->hreflangUseOnlyLocale = $hreflangUseOnlyLocale;
    }

    public function getSalesChannelDefaultHreflang(): ?SalesChannelEntity
    {
        return $this->salesChannelDefaultHreflang;
    }

    public function setSalesChannelDefaultHreflang(?SalesChannelEntity $salesChannelDefaultHreflang): void
    {
        $this->salesChannelDefaultHreflang = $salesChannelDefaultHreflang;
    }
}
