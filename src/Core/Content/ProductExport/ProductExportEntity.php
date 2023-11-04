<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('sales-channel')]
class ProductExportEntity extends Entity
{
    use EntityIdTrait;
    final public const ENCODING_UTF8 = 'UTF-8';
    final public const ENCODING_ISO88591 = 'ISO-8859-1';

    final public const FILE_FORMAT_CSV = 'csv';
    final public const FILE_FORMAT_XML = 'xml';

    /**
     * @var string
     */
    protected $productStreamId;

    /**
     * @var string
     */
    protected $storefrontSalesChannelId;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $salesChannelDomainId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var string
     */
    protected $fileFormat;

    /**
     * @var ProductStreamEntity
     */
    protected $productStream;

    /**
     * @var SalesChannelEntity
     */
    protected $storefrontSalesChannel;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var SalesChannelDomainEntity
     */
    protected $salesChannelDomain;

    /**
     * @var CurrencyEntity
     */
    protected $currency;

    /**
     * @var bool
     */
    protected $includeVariants;

    /**
     * @var bool
     */
    protected $generateByCronjob;

    /**
     * @var \DateTimeInterface|null
     */
    protected $generatedAt;

    /**
     * @var int
     */
    protected $interval;

    /**
     * @var string|null
     */
    protected $headerTemplate;

    /**
     * @var string|null
     */
    protected $bodyTemplate;

    /**
     * @var string|null
     */
    protected $footerTemplate;

    /**
     * @var bool|null
     */
    protected $pausedSchedule;

    /**
     * @var bool
     */
    protected $isRunning;

    public function getProductStreamId(): string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
    }

    public function getStorefrontSalesChannelId(): string
    {
        return $this->storefrontSalesChannelId;
    }

    public function setStorefrontSalesChannelId(string $storefrontSalesChannelId): void
    {
        $this->storefrontSalesChannelId = $storefrontSalesChannelId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannelDomainId(): string
    {
        return $this->salesChannelDomainId;
    }

    public function setSalesChannelDomainId(string $salesChannelDomainId): void
    {
        $this->salesChannelDomainId = $salesChannelDomainId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    public function getFileFormat(): string
    {
        return $this->fileFormat;
    }

    public function setFileFormat(string $fileFormat): void
    {
        $this->fileFormat = $fileFormat;
    }

    public function getProductStream(): ProductStreamEntity
    {
        return $this->productStream;
    }

    public function setProductStream(ProductStreamEntity $productStream): void
    {
        $this->productStream = $productStream;
    }

    public function getStorefrontSalesChannel(): SalesChannelEntity
    {
        return $this->storefrontSalesChannel;
    }

    public function setStorefrontSalesChannel(SalesChannelEntity $storefrontSalesChannel): void
    {
        $this->storefrontSalesChannel = $storefrontSalesChannel;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        return $this->salesChannelDomain;
    }

    public function setSalesChannelDomain(SalesChannelDomainEntity $salesChannelDomain): void
    {
        $this->salesChannelDomain = $salesChannelDomain;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function isIncludeVariants(): bool
    {
        return $this->includeVariants;
    }

    public function setIncludeVariants(bool $includeVariants): void
    {
        $this->includeVariants = $includeVariants;
    }

    public function isGenerateByCronjob(): bool
    {
        return $this->generateByCronjob;
    }

    public function setGenerateByCronjob(bool $generateByCronjob): void
    {
        $this->generateByCronjob = $generateByCronjob;
    }

    public function getGeneratedAt(): ?\DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeInterface $generatedAt): void
    {
        $this->generatedAt = $generatedAt;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    public function getHeaderTemplate(): ?string
    {
        return $this->headerTemplate;
    }

    public function setHeaderTemplate(?string $headerTemplate): void
    {
        $this->headerTemplate = $headerTemplate;
    }

    public function getBodyTemplate(): ?string
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(?string $bodyTemplate): void
    {
        $this->bodyTemplate = $bodyTemplate;
    }

    public function getFooterTemplate(): ?string
    {
        return $this->footerTemplate;
    }

    public function setFooterTemplate(?string $footerTemplate): void
    {
        $this->footerTemplate = $footerTemplate;
    }

    public function isPausedSchedule(): ?bool
    {
        return $this->pausedSchedule;
    }

    public function setPausedSchedule(?bool $pausedSchedule): void
    {
        $this->pausedSchedule = $pausedSchedule;
    }

    public function setIsRunning(bool $isRunning): void
    {
        $this->isRunning = $isRunning;
    }

    public function getIsRunning(): bool
    {
        return $this->isRunning;
    }
}
