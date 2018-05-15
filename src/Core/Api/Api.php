<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Framework\DependencyInjection\CompilerPass\DefinitionRegistryCompilerPass;
use Shopware\Framework\ORM\EntityDefinition;
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

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__));
        $this->loadEntities($loader);
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
