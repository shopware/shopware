<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpResourceTranslation;

use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppMcpResourceTranslationEntity extends TranslationEntity
{
    protected string $appMcpResourceId;

    protected ?AppMcpResourceEntity $appMcpResource = null;

    protected ?string $label = null;

    protected ?string $description = null;

    public function getAppMcpResourceId(): string
    {
        return $this->appMcpResourceId;
    }

    public function setAppMcpResourceId(string $appMcpResourceId): void
    {
        $this->appMcpResourceId = $appMcpResourceId;
    }

    public function getAppMcpResource(): ?AppMcpResourceEntity
    {
        return $this->appMcpResource;
    }

    public function setAppMcpResource(?AppMcpResourceEntity $appMcpResource): void
    {
        $this->appMcpResource = $appMcpResource;
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
}
