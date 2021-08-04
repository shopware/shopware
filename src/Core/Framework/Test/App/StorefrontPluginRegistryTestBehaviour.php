<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait StorefrontPluginRegistryTestBehaviour
{
    /**
     * @before
     */
    public function clearStorefrontAppRegistryCache(): void
    {
        if (!$this->getContainer()->has(StorefrontPluginRegistry::class)) {
            return;
        }
        $registry = $this->getContainer()
            ->get(StorefrontPluginRegistry::class);

        $reflection = new \ReflectionClass($registry);
        $prop = $reflection->getProperty('pluginConfigurations');

        $prop->setAccessible(true);
        $prop->setValue($registry, null);
    }

    /**
     * @before
     */
    public function clearEntityTemplateLoaderDatabaseCache(): void
    {
        $templateLoader = $this->getContainer()
            ->get(EntityTemplateLoader::class);

        $reflection = new \ReflectionClass($templateLoader);
        $prop = $reflection->getProperty('databaseTemplateCache');

        $prop->setAccessible(true);
        $prop->setValue($templateLoader, []);
    }

    /**
     * @before
     */
    public function clearTemplateFinderNamespaceHierarchyCache(): void
    {
        $templateFinder = $this->getContainer()
            ->get(TemplateFinder::class);

        $reflection = new \ReflectionClass($templateFinder);
        $prop = $reflection->getProperty('namespaceHierarchy');

        $prop->setAccessible(true);
        $prop->setValue($templateFinder, []);
    }

    abstract protected function getContainer(): ContainerInterface;
}
