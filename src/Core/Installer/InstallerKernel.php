<?php declare(strict_types=1);

namespace Shopware\Core\Installer;

use Composer\InstalledVersions;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\HttpKernelResult;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\Maintenance;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @internal
 */
class InstallerKernel extends HttpKernel
{
    use MicroKernelTrait;

    /**
     * @var string Regex pattern for validating Shopware versions
     */
    private const VALID_VERSION_PATTERN = '#^\d\.\d+\.\d+\.(\d+|x)(-\w+)?#';

    private string $shopwareVersion;

    private ?string $shopwareVersionRevision;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        if (InstalledVersions::isInstalled('shopware/platform')) {
            $version = InstalledVersions::getVersion('shopware/platform')
                . '@' . InstalledVersions::getReference('shopware/platform');
        } else {
            $version = InstalledVersions::getVersion('shopware/core')
                . '@' . InstalledVersions::getReference('shopware/core');
        }

        $this->parseShopwareVersion($version ?? Kernel::SHOPWARE_FALLBACK_VERSION);
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

    /**
     * @return string
     */
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

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true)
    {
        $response = parent::handle($request, $type, $catch);

        return new HttpKernelResult($request, $response);
    }

    /**
     * {@inheritdoc}
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

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ .'/Resources/config/routes.xml');
    }

    private function parseShopwareVersion(?string $version): void
    {
        // does not come from composer, was set manually
        if ($version === null || mb_strpos($version, '@') === false) {
            $this->shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;
            $this->shopwareVersionRevision = str_repeat('0', 32);

            return;
        }

        [$version, $hash] = explode('@', $version);
        $version = ltrim($version, 'v');
        $version = str_replace('+', '-', $version);

        /*
         * checks if the version is a valid version pattern
         * Shopware\Core\Framework\Test\KernelTest::testItCreatesShopwareVersion()
         */
        if (!preg_match($this::VALID_VERSION_PATTERN, $version)) {
            $version = Kernel::SHOPWARE_FALLBACK_VERSION;
        }

        $this->shopwareVersion = $version;
        $this->shopwareVersionRevision = $hash;
    }
}
