<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SimplePlugin extends Bundle implements PluginInterface
{
    use PluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerContainerFile($container);
    }

    protected function getServicesFilePath(): string
    {
        return 'Resources/config/services.xml';
    }

    protected function registerContainerFile(ContainerBuilder $container): void
    {
        $fileSystem = new Filesystem();
        $containerFilePath = ltrim($this->getServicesFilePath(), '/');

        if (!$fileSystem->exists($this->getPath() . '/' . $containerFilePath)) {
            return;
        }

        $loader = new XmlFileLoader($container, new FileLocator($this->getPath()));
        $loader->load($containerFilePath);
    }
}
