<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass
 */
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
        /** @var ContainerBuilder&MockObject $containerMock */
        $containerMock = $this->createMock(ContainerBuilder::class);

        /** @var Definition&MockObject $filesystemLoaderMock */
        $filesystemLoaderMock = $this->createMock(Definition::class);

        $containerMock->expects(static::exactly(3))->method('getParameter')->withConsecutive(
            ['kernel.bundles_metadata'],
            ['kernel.environment'],
            ['kernel.project_dir']
        )->willReturnOnConsecutiveCalls(
            [],
            'dev',
            __DIR__
        );

        $containerMock->method('findDefinition')->with('twig.loader.native_filesystem')->willReturn($filesystemLoaderMock);

        $connectionMock = $this->createMock(Connection::class);

        $containerMock->expects(static::once())->method('get')->with(Connection::class)->willReturn($connectionMock);

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

        $filesystemLoaderMock->expects(static::exactly(8))->method('addMethodCall');

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($containerMock);
    }

    public function testDevModeNoPluginsAndApps(): void
    {
        /** @var ContainerBuilder&MockObject $containerMock */
        $containerMock = $this->createMock(ContainerBuilder::class);

        /** @var Definition&MockObject $filesystemLoaderMock */
        $filesystemLoaderMock = $this->createMock(Definition::class);

        $containerMock->expects(static::exactly(3))->method('getParameter')->withConsecutive(
            ['kernel.bundles_metadata'],
            ['kernel.environment'],
            ['kernel.project_dir']
        )->willReturnOnConsecutiveCalls(
            [
                'pluginOne' => [
                    'path' => __DIR__ . '/fixtures/pluginOnePath',
                ],
            ],
            'dev',
            __DIR__
        );

        $containerMock->method('findDefinition')->with('twig.loader.native_filesystem')->willReturn($filesystemLoaderMock);

        $connectionMock = $this->createMock(Connection::class);

        $containerMock->expects(static::once())->method('get')->with(Connection::class)->willReturn($connectionMock);

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

        $filesystemLoaderMock->expects(static::exactly(12))->method('addMethodCall');

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($containerMock);
    }
}
