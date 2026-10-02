<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\TwigFieldVisibilityTest
 */
#[Package('framework')]
class WebhookEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $eventName;

    protected string $url;

    protected bool $onlyLiveVersion;

    protected ?string $appId = null;

    /**
     * @internal
     */
    protected ?string $ownerUserId = null;

    /**
     * @internal
     */
    protected ?string $ownerIntegrationId = null;

    protected bool $active;

    protected int $errorCount;

    protected ?AppEntity $app = null;

    /**
     * @var list<string>|null
     */
    protected ?array $aclRoleIds = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getOnlyLiveVersion(): bool
    {
        return $this->onlyLiveVersion;
    }

    public function setOnlyLiveVersion(bool $onlyLiveVersion): void
    {
        $this->onlyLiveVersion = $onlyLiveVersion;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
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

    /**
     * @internal
     */
    public function getOwnerUserId(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('ownerUserId');

        return $this->ownerUserId;
    }

    /**
     * @internal
     */
    public function setOwnerUserId(?string $ownerUserId): void
    {
        $this->ownerUserId = $ownerUserId;
    }

    /**
     * @internal
     */
    public function getOwnerIntegrationId(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('ownerIntegrationId');

        return $this->ownerIntegrationId;
    }

    /**
     * @internal
     */
    public function setOwnerIntegrationId(?string $ownerIntegrationId): void
    {
        $this->ownerIntegrationId = $ownerIntegrationId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): void
    {
        $this->errorCount = $errorCount;
    }

    /**
     * @return list<string>|null
     */
    public function getAclRoleIds(): ?array
    {
        return $this->aclRoleIds;
    }

    /**
     * @param list<string>|null $aclRoleIds
     */
    public function setAclRoleIds(?array $aclRoleIds): void
    {
        $this->aclRoleIds = $aclRoleIds;
    }
}
