<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\DependencyInjection\CompilerPass\DefinitionRegistryCompilerPass;
use Shopware\Api\Entity\EntityDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Api extends Bundle
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new DefinitionRegistryCompilerPass());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }

    public function boot()
    {
        parent::boot();

        $registry = $this->container->get('shopware.api.entity.extension_registry');
        foreach ($registry->getExtensions() as $extension) {
            /** @var EntityDefinition $definition */
            $definition = $extension->getDefinitionClass();
            $definition::addExtension($extension);
        }
    }
}
