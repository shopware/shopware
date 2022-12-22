<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package system-settings
 */
class SystemConfigDomainLoadedEvent extends Event
{
    private array $config;

    private string $domain;

    private bool $inherit;

    private ?string $salesChannelId;

    public function __construct(string $domain, array $config, bool $inherit, ?string $salesChannelId)
    {
        $this->config = $config;
        $this->domain = $domain;
        $this->inherit = $inherit;
        $this->salesChannelId = $salesChannelId;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isInherit(): bool
    {
        return $this->inherit;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
