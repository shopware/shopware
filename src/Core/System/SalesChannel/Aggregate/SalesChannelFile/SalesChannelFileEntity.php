<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('framework')]
class SalesChannelFileEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;

    protected ?SalesChannelEntity $salesChannel = null;

    protected string $fileFamily;

    protected string $fileName;

    protected bool $enabled = false;

    /**
     * @var array<string, string>
     */
    protected array $templateOverrides = [];

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getFileFamily(): string
    {
        return $this->fileFamily;
    }

    public function setFileFamily(string $fileFamily): void
    {
        $this->fileFamily = $fileFamily;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateOverrides(): array
    {
        return $this->templateOverrides;
    }

    /**
     * @param array<string, string> $templateOverrides
     */
    public function setTemplateOverrides(array $templateOverrides): void
    {
        $this->templateOverrides = $templateOverrides;
    }
}
