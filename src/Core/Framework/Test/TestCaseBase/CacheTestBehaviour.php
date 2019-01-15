<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

trait CacheTestBehaviour
{
    /**
     * @before
     */
    public function clearCacheBefore(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get('shopware.cache')
            ->clear();
    }
}
