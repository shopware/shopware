<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

interface SystemConfigServiceInterface
{
    public function get(string $key, ?string $salesChannelId = null);

    public function getDomain(string $domain, ?string $salesChannelId = null): array;

    public function set(string $key, $value, ?string $salesChannelId = null): void;
}
