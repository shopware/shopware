<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

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
     * @deprecated tag:v6.8.0 - Legacy BC mirror of the endpoint health state; read `endpoint_state` via the webhook health API (GET /api/app-system/webhook/state) instead. Removed with WEBHOOKS_REWORK.
     */
    protected bool $active;

    /**
     * @deprecated tag:v6.8.0 - Legacy shared failure counter; read `consecutiveTransientFailures` via the webhook health API instead. Removed with WEBHOOKS_REWORK.
     */
    protected int $errorCount;

    protected ?AppEntity $app = null;

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
     * @deprecated tag:v6.8.0 - Legacy BC mirror of the endpoint health state; read `endpoint_state` via the webhook health API (GET /api/app-system/webhook/state) instead. Removed with WEBHOOKS_REWORK.
     *
     * @phpstan-ignore shopware.deprecatedMethod (BC-mirror accessor during the WEBHOOKS_REWORK rollout; a runtime notice would pollute logs — the @deprecated tag + v6.8.0 runbook are the cutover signal)
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @deprecated tag:v6.8.0 - Legacy BC mirror of the endpoint health state; the health model owns the active flag under WEBHOOKS_REWORK. Removed with the flag.
     *
     * @phpstan-ignore shopware.deprecatedMethod (BC-mirror accessor during the WEBHOOKS_REWORK rollout; a runtime notice would pollute logs — the @deprecated tag + v6.8.0 runbook are the cutover signal)
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @deprecated tag:v6.8.0 - Legacy shared failure counter; read `consecutiveTransientFailures` via the webhook health API instead. Removed with WEBHOOKS_REWORK.
     *
     * @phpstan-ignore shopware.deprecatedMethod (BC-mirror accessor during the WEBHOOKS_REWORK rollout; a runtime notice would pollute logs — the @deprecated tag + v6.8.0 runbook are the cutover signal)
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * @deprecated tag:v6.8.0 - Legacy shared failure counter; the health model owns failure counting under WEBHOOKS_REWORK. Removed with the flag.
     *
     * @phpstan-ignore shopware.deprecatedMethod (BC-mirror accessor during the WEBHOOKS_REWORK rollout; a runtime notice would pollute logs — the @deprecated tag + v6.8.0 runbook are the cutover signal)
     */
    public function setErrorCount(int $errorCount): void
    {
        $this->errorCount = $errorCount;
    }
}
