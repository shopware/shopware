<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Elasticsearch\DependencyInjection\ElasticsearchExtension;
use Shopware\Elasticsearch\Profiler\ElasticsearchProfileCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @internal
 */
#[Package('core')]
class Elasticsearch extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Needs to run before the ProfilerPass
        $container->addCompilerPass(new ElasticsearchProfileCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 5000);

        $this->buildConfig($container);
    }

    protected function createContainerExtension(): ?ExtensionInterface
    {
        return new ElasticsearchExtension();
    }

    private function buildConfig(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');

        $env = $container->getParameter('kernel.environment');
        if (!\is_string($env)) {
            throw new \RuntimeException('Container parameter "kernel.environment" needs to be a string');
        }
        $configLoader->load($confDir . '/{packages}/' . $env . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }
}
