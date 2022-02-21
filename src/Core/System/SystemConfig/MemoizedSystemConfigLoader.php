<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;

class MemoizedSystemConfigLoader extends AbstractSystemConfigLoader
{
    private AbstractSystemConfigLoader $decorated;

    private MemoizedSystemConfigStore $memoizedSystemConfigStore;

    public function __construct(
        AbstractSystemConfigLoader $decorated,
        MemoizedSystemConfigStore $memoizedSystemConfigStore
    ) {
        $this->decorated = $decorated;
        $this->memoizedSystemConfigStore = $memoizedSystemConfigStore;
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
