<?php declare(strict_types=1);

namespace Shopware\Core\Installer;

use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
#[Package('core')]
class Installer extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TwigEnvironmentCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');

        $locator = new FileLocator($this->getPath() . '/Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $configLoader->load('{packages}/*.yaml', 'glob');
    }
}
