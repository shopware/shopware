<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
#[CoversClass(TwigLoaderConfigCompilerPass::class)]
class TwigLoaderConfigCompilerPassTest extends TestCase
{
    public function testDevModeNoPluginsOrApps(): void
    {
        $container = new ContainerBuilder();

        $container->register('twig.loader.native_filesystem', FilesystemLoader::class);
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.project_dir', '/project');

        $connectionMock = $this->createMock(Connection::class);

        $container->set(Connection::class, $connectionMock);

        $connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn([]);

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($container);
    }

    public function testDevModeNoPluginsButApps(): void
    {
        /** @var ContainerBuilder&MockObject $container */
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.project_dir', '/project');

        $filesystemLoaderDefinition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $filesystemLoaderDefinition);

        $connectionMock = $this->createMock(Connection::class);

        $container->set(Connection::class, $connectionMock);

        $connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn(
            [
                [
                    'name' => 'firstApp',
                    'path' => 'fixtures/firstAppPath',
                ],
                [
                    'name' => 'secondApp',
                    'path' => 'fixtures/secondAppPath',
                ],
            ]
        );

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($container);

        static::assertEmpty($filesystemLoaderDefinition->getMethodCalls(), 'no method calls expected, as no apps loaded');
    }

    public function testDevModeNoPluginsAndApps(): void
    {
        /** @var ContainerBuilder&MockObject $container */
        $container = new ContainerBuilder();

        /** @var Definition&MockObject $filesystemLoader */
        $filesystemLoader = new Definition(FilesystemLoader::class);

        $container->setParameter(
            'kernel.bundles_metadata',
            [
                'pluginOne' => [
                    'path' => __DIR__ . '/fixtures/pluginOnePath',
                ],
            ]
        );
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.project_dir', __DIR__);

        $container->setDefinition('twig.loader.native_filesystem', $filesystemLoader);

        $connectionMock = $this->createMock(Connection::class);

        $container->set(Connection::class, $connectionMock);

        $connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn(
            [
                [
                    'name' => 'firstApp',
                    'path' => 'fixtures/firstAppPath',
                ],
                [
                    'name' => 'secondApp',
                    'path' => 'fixtures/secondAppPath',
                ],
            ]
        );

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($container);

        $calls = $filesystemLoader->getMethodCalls();
        static::assertCount(12, $calls);
    }
}
