<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\DependencyInjection\ThemeCompilerAssetCompilerPass;
use Shopware\Storefront\DependencyInjection\TwigComponentBundlePass;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('discovery')]
class Storefront extends Bundle implements ThemeInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('content-system.php');
        $loader->load('services.php');
        $loader->load('captcha.php');
        $loader->load('seo.php');
        $loader->load('controller.php');
        $loader->load('theme.php');
        $loader->load('system.php');
        $loader->load('mcp.php');

        $container->setParameter('storefrontRoot', $this->getPath());

        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new StorefrontMigrationReplacementCompilerPass());
        $container->addCompilerPass(new ThemeCompilerAssetCompilerPass());
        // Auto-register Twig component namespaces for all bundles
        // Must run before Symfony's TwigComponentPass processes the configuration
        $container->addCompilerPass(new TwigComponentBundlePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
