<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class Storefront extends Bundle
{
    protected $name = 'Storefront';

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('profiling.xml');
        }

        $loader->load('seo.xml');

        $container->addCompilerPass(new DisableTemplateCachePass());
    }

    public function getViewPaths(): array
    {
        return [
            'Resources/views',
            'Administration/views',
        ];
    }

    public function getAdministrationEntryPath(): string
    {
        return 'Administration';
    }
}
