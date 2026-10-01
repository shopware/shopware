<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Installer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\InstallerKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @internal
 */
#[Package('framework')]
class InstallerKernelStub extends InstallerKernel
{
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $composerVersion = null,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeKernelParameters(): array
    {
        return $this->getKernelParameters();
    }

    public function exposeConfigureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $this->configureContainer($container, $loader);
    }

    public function exposeConfigureRoutes(RoutingConfigurator $routes): void
    {
        $this->configureRoutes($routes);
    }

    protected function resolveComposerVersion(): string
    {
        if ($this->composerVersion !== null) {
            return $this->composerVersion;
        }

        return parent::resolveComposerVersion();
    }
}
