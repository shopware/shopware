<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\SystemConfigService;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class StaticSystemConfigService extends SystemConfigService
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function get(string $key, ?string $salesChannelId = null)
    {
        if ($salesChannelId) {
            return $this->config[$salesChannelId][$key] ?? null;
        }

        return $this->config[$key] ?? null;
    }

    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        if ($salesChannelId) {
            $this->config[$salesChannelId][$key] = $value;

            return;
        }

        $this->config[$key] = $value;
    }
}
