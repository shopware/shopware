<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\DependencyInjection\CompilerPass\DefinitionRegistryCompilerPass;
use Shopware\Api\Entity\EntityDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;
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

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__));
        $loader->load('./DependencyInjection/services.xml');

        $this->loadEntities($loader);
    }

    public function boot()
    {
        parent::boot();

        $registry = $this->container->get(Entity\ExtensionRegistry::class);
        foreach ($registry->getExtensions() as $extension) {
            /** @var EntityDefinition $definition */
            $definition = $extension->getDefinitionClass();
            $definition::addExtension($extension);
        }
    }

    private function loadEntities(XmlFileLoader $loader): void
    {
        $finder = new Finder();

        $entities = $finder->in(__DIR__)->directories()->depth(0)->exclude(['Entity', 'Test', 'DependencyInjection'])->getIterator();

        foreach ($entities as $entityPath) {
            $file = __DIR__ . '/' . $entityPath->getFilename() . '/DependencyInjection/api.xml';

            if (!file_exists($file)) {
                continue;
            }

            $loader->load($file);
        }
    }
}
