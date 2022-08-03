<?php declare(strict_types=1);

namespace Shopware\Core\Installer;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Util\VersionParser;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @internal
 */
class InstallerKernel extends HttpKernel
{
    use MicroKernelTrait;

    private string $shopwareVersion;

    private ?string $shopwareVersionRevision;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        // @codeCoverageIgnoreStart - not testable, as static calls cannot be mocked
        if (InstalledVersions::isInstalled('shopware/platform')) {
            $version = InstalledVersions::getVersion('shopware/platform')
                . '@' . InstalledVersions::getReference('shopware/platform');
        } else {
            $version = InstalledVersions::getVersion('shopware/core')
                . '@' . InstalledVersions::getReference('shopware/core');
        }
        // @codeCoverageIgnoreEnd

        $version = VersionParser::parseShopwareVersion($version);
        $this->shopwareVersion = $version['version'];
        $this->shopwareVersionRevision = $version['revision'];
    }

    /**
     * @return \Generator<BundleInterface>
     */
    public function registerBundles(): \Generator
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new Installer();
    }

    public function getProjectDir(): string
    {
        $r = new \ReflectionObject($this);

        /** @var string $dir */
        $dir = $r->getFileName();
        if (!file_exists($dir)) {
            throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
        }

        $dir = $rootDir = \dirname($dir);
        while (!file_exists($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        return array_merge(
            $parameters,
            [
                'kernel.shopware_version' => $this->shopwareVersion,
                'kernel.shopware_version_revision' => $this->shopwareVersionRevision,
                'kernel.secret' => 'noSecr3t',
            ]
        );
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('container.dumper.inline_class_loader', true);
        $container->setParameter('container.dumper.inline_factories', true);

        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . Kernel::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . Kernel::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . Kernel::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/Resources/config/routes.xml');
    }
}
