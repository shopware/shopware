<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;

#[Package('system-settings')]
class MemoizedSystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly MemoizedSystemConfigStore $memoizedSystemConfigStore
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        $config = $this->memoizedSystemConfigStore->getConfig($salesChannelId);

        if ($config !== null) {
            return $config;
        }

        $config = $this->getDecorated()->load($salesChannelId);
        $this->memoizedSystemConfigStore->setConfig($salesChannelId, $config);

        return $config;
    }
}
