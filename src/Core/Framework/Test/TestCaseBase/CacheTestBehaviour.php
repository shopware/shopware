<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

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
            ->get('cache.object')
            ->clear();
    }

    abstract protected function getContainer(): ContainerInterface;
}
