<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig\_fixtures\MemoizedSystemConfigLoaderTest;

use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;

class DecoratedMemoizedResetTestSystemConfigLoader extends AbstractSystemConfigLoader
{
    private AbstractSystemConfigLoader $decorated;

    public function __construct(AbstractSystemConfigLoader $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        return $this->getDecorated()->load($salesChannelId);
    }
}
