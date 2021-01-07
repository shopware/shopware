<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Cart\Address\AddressValidator;
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

        $property = (new \ReflectionClass(AddressValidator::class))
            ->getProperty('available');

        $property->setAccessible(true);

        $property->setValue($this->getContainer()->get(AddressValidator::class), []);
    }

    abstract protected function getContainer(): ContainerInterface;
}
