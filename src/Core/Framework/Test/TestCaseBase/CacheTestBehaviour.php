<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CacheTestBehaviour
{
    /**
     * @before
     */
    public function clearCacheBefore(): void
    {
        $this->getContainer()
            ->get('test.service_container')
            ->get(CacheClearer::class)
            ->clear();
    }

    /**
     * @after
     */
    public function clearCacheAfter(): void
    {
        $this->getContainer()
            ->get('test.service_container')
            ->get(CacheClearer::class)
            ->clear();
    }

    abstract protected function getContainer(): ContainerInterface;
}
