<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class TwigLoaderConfigCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $fileSystemLoader = $container->findDefinition('twig.loader.native_filesystem');

        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundlesMetadata)) {
            throw new \RuntimeException('Container parameter "kernel.bundles_metadata" needs to be an array');
        }

        foreach ($bundlesMetadata as $name => $bundle) {
            $directory = $bundle['path'] . '/Resources/views';
            if (!file_exists($directory)) {
                continue;
            }

            $fileSystemLoader->addMethodCall('addPath', [$directory]);
            $fileSystemLoader->addMethodCall('addPath', [$directory, $name]);
        }

        // App templates are only loaded in dev env from files
        // on prod they are loaded from DB as the app files might not exist locally
        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->addAppTemplatePaths($container, $fileSystemLoader);
        }
    }

    private function addAppTemplatePaths(ContainerBuilder $container, Definition $fileSystemLoader): void
    {
        $connection = $container->get(Connection::class);

        try {
            $apps = $connection->fetchAll('SELECT `name`, `path` FROM `app` WHERE `active` = 1');
        } catch (\Doctrine\DBAL\DBALException $e) {
            // If DB is not yet set up correctly we don't need to add app paths
            return;
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if (!\is_string($projectDir)) {
            throw new \RuntimeException('Container parameter "kernel.project_dir" needs to be a string');
        }

        foreach ($apps as $app) {
            $directory = sprintf('%s/%s/Resources/views', $projectDir, $app['path']);

            if (!file_exists($directory)) {
                continue;
            }

            $fileSystemLoader->addMethodCall('addPath', [$directory]);
            $fileSystemLoader->addMethodCall('addPath', [$directory, $app['name']]);
        }
    }
}
