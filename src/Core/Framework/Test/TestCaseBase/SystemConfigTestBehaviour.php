<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait SystemConfigTestBehaviour
{
    /**
     * necessary because some tests may lead to saved system config values
     *
     * @before
     * @after
     */
    public function resetInternalSystemConfigCache(): void
    {
        /** @var MemoizedSystemConfigStore $store */
        $store = $this->getContainer()->get(MemoizedSystemConfigStore::class);
        $store->reset();
    }

    abstract protected function getContainer(): ContainerInterface;
}
