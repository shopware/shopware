<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig\_fixtures\MemoizedSystemConfigLoaderTest;

use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;

/**
 * @internal
 */
class DecoratedMemoizedResetTestSystemConfigLoader extends AbstractSystemConfigLoader
{
    public function __construct(private readonly AbstractSystemConfigLoader $decorated)
    {
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
