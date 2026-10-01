<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpResource;

use Shopware\Core\Framework\App\Aggregate\AppMcpResourceTranslation\AppMcpResourceTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppMcpResourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $uri;

    protected string $url;

    protected ?string $mimeType = null;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?AppMcpResourceTranslationCollection $translations = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
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

    public function getTranslations(): ?AppMcpResourceTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppMcpResourceTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
