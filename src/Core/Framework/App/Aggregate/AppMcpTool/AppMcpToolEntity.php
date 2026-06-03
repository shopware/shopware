<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpTool;

use Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation\AppMcpToolTranslationCollection;
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
class AppMcpToolEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $url;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $inputSchema = null;

    /**
     * @var list<string>|null
     */
    protected ?array $requiredPrivileges = null;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?AppMcpToolTranslationCollection $translations = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return list<string>|null
     */
    public function getRequiredPrivileges(): ?array
    {
        return $this->requiredPrivileges;
    }

    /**
     * @param list<string>|null $requiredPrivileges
     */
    public function setRequiredPrivileges(?array $requiredPrivileges): void
    {
        $this->requiredPrivileges = $requiredPrivileges;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInputSchema(): ?array
    {
        return $this->inputSchema;
    }

    /**
     * @param array<string, mixed>|null $inputSchema
     */
    public function setInputSchema(?array $inputSchema): void
    {
        $this->inputSchema = $inputSchema;
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

    public function getTranslations(): ?AppMcpToolTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppMcpToolTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
