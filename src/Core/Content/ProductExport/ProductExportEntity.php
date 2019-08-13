<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ProductExportEntity extends Entity
{
    use EntityIdTrait;
    public const ENCODING_UTF8 = 'utf-8';
    public const ENCODING_ISO88591 = 'iso-8859-1';

    public const FILE_FORMAT_CSV = 'csv';
    public const FILE_FORMAT_XML = 'xml';

    /** @var string */
    protected $productStreamId;

    /** @var string */
    protected $salesChannelId;

    /** @var string */
    protected $salesChannelDomainId;

    /** @var string */
    protected $fileName;

    /** @var string */
    protected $accessToken;

    /** @var string */
    protected $encoding;

    /** @var string */
    protected $fileFormat;

    /** @var ProductStreamEntity */
    protected $productStream;

    /** @var SalesChannelEntity */
    protected $salesChannel;

    /** @var SalesChannelDomainEntity */
    protected $salesChannelDomain;

    /** @var bool */
    protected $includeVariants;

    /** @var bool */
    protected $generateByCronjob;

    /** @var \DateTimeInterface */
    protected $lastGeneration;

    /** @var int */
    protected $interval;

    /** @var string */
    protected $headerTemplate;

    /** @var string */
    protected $bodyTemplate;

    /** @var string */
    protected $footerTemplate;

    public function getProductStreamId(): string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
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

    public function getLastGeneration(): \DateTimeInterface
    {
        return $this->lastGeneration;
    }

    public function setLastGeneration(\DateTimeInterface $lastGeneration): void
    {
        $this->lastGeneration = $lastGeneration;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    public function getHeaderTemplate(): string
    {
        return $this->headerTemplate;
    }

    public function setHeaderTemplate(string $headerTemplate): void
    {
        $this->headerTemplate = $headerTemplate;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(string $bodyTemplate): void
    {
        $this->bodyTemplate = $bodyTemplate;
    }

    public function getFooterTemplate(): string
    {
        return $this->footerTemplate;
    }

    public function setFooterTemplate(string $footerTemplate): void
    {
        $this->footerTemplate = $footerTemplate;
    }
}
