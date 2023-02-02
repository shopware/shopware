<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowAction;

use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppFlowActionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected string $name;

    protected ?string $badge = null;

    protected string $label;

    protected ?string $description = null;

    protected ?string $headline = null;

    /**
     * @var array<string, mixed>
     */
    protected array $parameters;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @var array<string, mixed>
     */
    protected array $headers;

    /**
     * @var array<string>
     */
    protected array $requirements;

    protected ?string $iconRaw = null;

    protected ?string $icon = null;

    protected ?string $swIcon = null;

    protected string $url;

    protected bool $delayable = false;

    protected ?AppFlowActionTranslationCollection $translations = null;

    protected ?FlowSequenceCollection $flowSequences = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(string $badge): void
    {
        $this->badge = $badge;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return array<string>
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    /**
     * @param array<string> $requirements
     */
    public function setRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): void
    {
        $this->headline = $headline;
    }

    public function getIconRaw(): ?string
    {
        return $this->iconRaw;
    }

    public function setIconRaw(?string $iconRaw): void
    {
        $this->iconRaw = $iconRaw;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getSwIcon(): ?string
    {
        return $this->swIcon;
    }

    public function setSwIcon(?string $swIcon): void
    {
        $this->swIcon = $swIcon;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getDelayable(): bool
    {
        return $this->delayable;
    }

    public function setDelayable(bool $delayable): void
    {
        $this->delayable = $delayable;
    }

    public function getTranslations(): ?AppFlowActionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppFlowActionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getFlowSequences(): ?FlowSequenceCollection
    {
        return $this->flowSequences;
    }

    public function setFlowSequences(FlowSequenceCollection $flowSequences): void
    {
        $this->flowSequences = $flowSequences;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $serializedData = parent::jsonSerialize();
        unset($serializedData['iconRaw']);

        return $serializedData;
    }
}
