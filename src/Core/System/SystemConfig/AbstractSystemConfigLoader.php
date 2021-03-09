<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

abstract class AbstractSystemConfigLoader
{
    abstract public function getDecorated(): AbstractSystemConfigLoader;

    abstract public function load(?string $salesChannelId): array;
}
