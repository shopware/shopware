<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class SystemConfigDomainLoadedEvent extends Event
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $domain,
        private array $config,
        private readonly bool $inherit,
        private readonly ?string $salesChannelId
    ) {
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
