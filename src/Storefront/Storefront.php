<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\DependencyInjection\TwigComponentBundlePass;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('framework')]
class Storefront extends Bundle implements ThemeInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');
        $loader->load('captcha.xml');
        $loader->load('seo.xml');
        $loader->load('controller.xml');
        $loader->load('theme.xml');
        $loader->load('system.xml');

        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $phpLoader->load('mcp.php');

        $container->setParameter('storefrontRoot', $this->getPath());

        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new StorefrontMigrationReplacementCompilerPass());
        // Auto-register Twig component namespaces for all bundles
        // Must run before Symfony's TwigComponentPass processes the configuration
        $container->addCompilerPass(new TwigComponentBundlePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
