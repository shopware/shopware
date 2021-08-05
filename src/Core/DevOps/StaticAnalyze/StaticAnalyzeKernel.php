<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze;

use Shopware\Core\Kernel;

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
