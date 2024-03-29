<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[Package('core')]
class StaticAnalyzeKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return sprintf(
            '%s/var/cache/%s',
            $this->getProjectDir(),
            $this->getEnvironment()
        );
    }
}
