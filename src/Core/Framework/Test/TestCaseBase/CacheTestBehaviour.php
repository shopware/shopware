<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCacheClearer;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
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

        $this->resetInternalCache(CachedFlowLoader::class, 'flows', []);

        $this->resetInternalCache(LanguageLocaleCodeProvider::class, 'languages', []);

        $this->resetInternalCache(ScriptTraces::class, 'traces', []);
        $this->resetInternalCache(ScriptTraces::class, 'data', []);

        $this->resetInternalCache(TemplateFinder::class, 'namespaceHierarchy', []);
    }

    abstract protected function getContainer(): ContainerInterface;

    private function resetInternalCache(string $class, string $property, $value): void
    {
        $instance = $this->getContainer()->get($class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if ($instance === null) {
            // may happen if we want to clear the internal cache of bundles that are not installed in every test run, e.g. storefront services
            return;
        }

        $property = (new \ReflectionClass($class))->getProperty($property);

        $property->setAccessible(true);

        $property->setValue($instance, $value);
    }
}
