<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Framework\Test\TestCacheClearer;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CacheTestBehaviour
{
    /**
     * @before
     *
     * @after
     */
    public function clearCacheData(): void
    {
        /** @var TestCacheClearer $cacheClearer */
        $cacheClearer = $this->getContainer()->get(TestCacheClearer::class);
        $cacheClearer->clear();

        $this->getContainer()
            ->get('services_resetter')
            ->reset();
    }

    abstract protected static function getContainer(): ContainerInterface;
}
