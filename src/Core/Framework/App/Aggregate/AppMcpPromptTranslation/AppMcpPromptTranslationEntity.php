<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpPromptTranslation;

use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppMcpPromptTranslationEntity extends TranslationEntity
{
    protected string $appMcpPromptId;

    protected ?AppMcpPromptEntity $appMcpPrompt = null;

    protected ?string $label = null;

    protected ?string $description = null;

    public function getAppMcpPromptId(): string
    {
        return $this->appMcpPromptId;
    }

    public function setAppMcpPromptId(string $appMcpPromptId): void
    {
        $this->appMcpPromptId = $appMcpPromptId;
    }

    public function getAppMcpPrompt(): ?AppMcpPromptEntity
    {
        return $this->appMcpPrompt;
    }

    public function setAppMcpPrompt(?AppMcpPromptEntity $appMcpPrompt): void
    {
        $this->appMcpPrompt = $appMcpPrompt;
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
