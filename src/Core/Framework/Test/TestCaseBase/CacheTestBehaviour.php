<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
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

        $this->resetInternalCache(AddressValidator::class, 'available', []);

        $this->resetInternalCache(ProductPriceCalculator::class, 'units', null);
    }

    abstract protected function getContainer(): ContainerInterface;

    private function resetInternalCache(string $class, string $property, $value): void
    {
        $property = (new \ReflectionClass($class))->getProperty($property);

        $property->setAccessible(true);

        $property->setValue($this->getContainer()->get($class), $value);
    }
}
