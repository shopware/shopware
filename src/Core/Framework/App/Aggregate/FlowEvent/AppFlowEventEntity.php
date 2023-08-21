<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowEvent;

use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppFlowEventEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected string $name;

    /**
     * @var array<string>
     */
    protected array $aware;

    protected ?FlowCollection $flows = null;

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

    /**
     * @return array<string>
     */
    public function getAware(): array
    {
        return $this->aware;
    }

    /**
     * @param array<string> $aware
     */
    public function setAware(array $aware): void
    {
        $this->aware = $aware;
    }

    public function getFlows(): ?FlowCollection
    {
        return $this->flows;
    }

    public function setFlows(FlowCollection $flows): void
    {
        $this->flows = $flows;
    }

    public function jsonSerialize(): array
    {
        return parent::jsonSerialize();
    }
}
