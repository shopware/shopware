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
            ->get('shopware.cache')
            ->clear();
    }

    abstract protected function getContainer(): ContainerInterface;
}
