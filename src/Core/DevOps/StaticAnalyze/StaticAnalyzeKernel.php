<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[Package('framework')]
class StaticAnalyzeKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return \sprintf(
            '%s/var/cache/static_%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
        );
    }

    /**
     * @return array<string, array<string, mixed>|bool|string|int|float|\UnitEnum|null>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        // Perf improvement: skip dumping debug container info in static analysis
        // see: https://symfony.com/doc/current/performance.html#disable-dumping-the-container-as-xml-in-debug-mode
        $parameters['debug.container.dump'] = false;

        return $parameters;
    }
}
