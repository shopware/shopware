<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Installer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\InstallerKernel;

/**
 * @internal
 */
#[Package('framework')]
class InstallerKernelStub extends InstallerKernel
{
    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $composerVersion,
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

    protected function resolveComposerVersion(): string
    {
        return $this->composerVersion;
    }
}
