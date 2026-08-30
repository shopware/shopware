<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\Attributes\After;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait EnvTestBehaviour
{
    /**
     * @var array<string, string|int|bool|null>
     */
    private array $originalEnvVars = [];

    /**
     * A null value removes the variable for the duration of the test.
     *
     * Services the container has already built keep the value they were injected with; boot a
     * fresh kernel when the test needs those rebuilt.
     *
     * @param array<string, string|int|bool|null> $envVars
     */
    public function setEnvVars(array $envVars): void
    {
        if ($envVars === []) {
            return;
        }

        foreach ($envVars as $envVar => $value) {
            if (!\array_key_exists($envVar, $this->originalEnvVars)) {
                $this->originalEnvVars[$envVar] = $_SERVER[$envVar] ?? null;
            }

            $this->applyEnvVar($envVar, $value);
        }

        KernelLifecycleManager::resetContainerEnvCache();
    }

    /**
     * Restores the environment and drops the kernel, so nothing the container resolved from the
     * changed variables survives the test.
     */
    #[After]
    public function resetEnvVars(): void
    {
        if ($this->originalEnvVars === []) {
            return;
        }

        foreach ($this->originalEnvVars as $envVar => $value) {
            $this->applyEnvVar($envVar, $value);
        }

        $this->originalEnvVars = [];

        KernelLifecycleManager::ensureKernelShutdown();
    }

    private function applyEnvVar(string $envVar, string|int|bool|null $value): void
    {
        if ($value === null) {
            // putenv("NAME=") would not remove the variable but set it to an empty string
            // in the real environment, where spawned processes (e.g. RunInSeparateProcess
            // children) would still see it
            unset($_SERVER[$envVar], $_ENV[$envVar]);
            putenv($envVar);

            return;
        }

        $_SERVER[$envVar] = $value;
        $_ENV[$envVar] = $value;
        putenv("{$envVar}={$value}");
    }
}
