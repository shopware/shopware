<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppMcpToolTranslationEntity extends TranslationEntity
{
    protected string $appMcpToolId;

    protected ?string $label = null;

    protected ?string $description = null;

    public function getAppMcpToolId(): string
    {
        return $this->appMcpToolId;
    }

    public function setAppMcpToolId(string $appMcpToolId): void
    {
        $this->appMcpToolId = $appMcpToolId;
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
