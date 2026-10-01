<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AdminInfoConfigEvent
{
    /**
     * @internal Constructor for internal use only.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config
    ) {
    }

    public function addConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
