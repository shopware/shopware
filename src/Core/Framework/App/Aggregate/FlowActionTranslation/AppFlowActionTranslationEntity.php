<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowActionTranslation;

use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

/**
 * @internal
 */
class AppFlowActionTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $label;

    protected ?string $description;

    protected string $appFlowActionId;

    protected ?AppFlowActionEntity $appFlowAction = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
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

    public function getAppFlowActionId(): string
    {
        return $this->appFlowActionId;
    }

    public function setAppFlowActionId(string $appFlowActionId): void
    {
        $this->appFlowActionId = $appFlowActionId;
    }

    public function getAppFlowAction(): ?AppFlowActionEntity
    {
        return $this->appFlowAction;
    }

    public function setAppFlowAction(?AppFlowActionEntity $appFlowAction): void
    {
        $this->appFlowAction = $appFlowAction;
    }
}
