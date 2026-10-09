<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\DevOps\Core\Installer\InstallerKernelTest
 */
class SeeDevOpsTestClass
{
    public function boot(): void
    {
        $this->ensureEnvironment();
    }

    private function ensureEnvironment(): void
    {
    }
}
