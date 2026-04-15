<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Installer;
use Shopware\Core\Installer\InstallerKernel;
use Shopware\Core\Test\Stub\Installer\InstallerKernelStub;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(InstallerKernel::class)]
class InstallerKernelTest extends TestCase
{
    #[TestDox('constructor resolves version from shopware/platform when installed')]
    public function testConstructorUsesPlatformVersionWhenInstalled(): void
    {
        $kernel = new InstallerKernelStub('test', false, '6.6.0.0@abc123platform');
        $params = $kernel->exposeKernelParameters();

        static::assertSame('6.6.0.0', $params['kernel.shopware_version']);
        static::assertSame('abc123platform', $params['kernel.shopware_version_revision']);
    }

    #[TestDox('constructor falls back to shopware/core version when platform is not installed')]
    public function testConstructorUsesCoreVersionWhenPlatformNotInstalled(): void
    {
        $kernel = new InstallerKernelStub('test', false, '6.6.1.0@def456core');
        $params = $kernel->exposeKernelParameters();

        static::assertSame('6.6.1.0', $params['kernel.shopware_version']);
        static::assertSame('def456core', $params['kernel.shopware_version_revision']);
    }

    #[TestDox('registerBundles yields FrameworkBundle, TwigBundle and Installer')]
    public function testRegisterBundlesYieldsExpectedBundles(): void
    {
        $kernel = new InstallerKernel('test', false);

        $bundles = iterator_to_array($kernel->registerBundles());

        static::assertCount(3, $bundles);
        static::assertInstanceOf(FrameworkBundle::class, $bundles[0]);
        static::assertInstanceOf(TwigBundle::class, $bundles[1]);
        static::assertInstanceOf(Installer::class, $bundles[2]);
    }

    #[TestDox('getProjectDir finds the directory containing vendor')]
    public function testGetProjectDirFindsVendorDirectory(): void
    {
        $kernel = new InstallerKernel('test', false);

        $projectDir = $kernel->getProjectDir();

        static::assertDirectoryExists($projectDir . '/vendor');
    }

    #[TestDox('configureContainer loads the installer yaml config')]
    public function testConfigureContainerLoadsInstallerYaml(): void
    {
        $kernel = new InstallerKernelStub('test', false, '6.6.0.0@abc123');

        $container = new ContainerBuilder();
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->with(static::stringEndsWith('/Framework/Resources/config/packages/installer.yaml'));

        $kernel->exposeConfigureContainer($container, $loader);
    }

    #[TestDox('configureRoutes imports the installer routes xml')]
    public function testConfigureRoutesImportsRoutesXml(): void
    {
        $kernel = new InstallerKernelStub('test', false, '6.6.0.0@abc123');

        $loader = $this->createMock(PhpFileLoader::class);
        $loader->expects($this->once())
            ->method('import')
            ->with(static::stringEndsWith('/Installer/Resources/config/routes.xml'))
            ->willReturn(new RouteCollection());

        $routes = new RoutingConfigurator(new RouteCollection(), $loader, '', '');

        $kernel->exposeConfigureRoutes($routes);
    }
}
