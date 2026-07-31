<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentType;

use Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation\AppDocumentTypeTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AppDocumentTypeEntity extends Entity
{
    use EntityIdTrait;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected string $technicalName;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $config = null;

    /**
     * @var list<string>|null
     */
    protected ?array $formats = null;

    protected ?string $label = null;

    protected ?AppDocumentTypeTranslationCollection $translations = null;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return list<string>|null
     */
    public function getFormats(): ?array
    {
        return $this->formats;
    }

    /**
     * @param list<string>|null $formats
     */
    public function setFormats(?array $formats): void
    {
        $this->formats = $formats;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTranslations(): ?AppDocumentTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppDocumentTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
