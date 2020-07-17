<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Framework\Test\TestCacheClearer;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CacheTestBehaviour
{
    /**
     * @before
     * @after
     */
    public function clearCacheData(): void
    {
        $this->getContainer()
            ->get('test.service_container')
            ->get(TestCacheClearer::class)
            ->clear();
    }

    abstract protected function getContainer(): ContainerInterface;
}
