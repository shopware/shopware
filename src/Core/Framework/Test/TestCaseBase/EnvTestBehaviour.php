<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

trait EnvTestBehaviour
{
    private $originalEnvVars = [];

    public function setEnvVars(array $envVars): void
    {
        foreach ($envVars as $envVar => $value) {
            if (!\array_key_exists($envVar, $this->originalEnvVars)) {
                $this->originalEnvVars[$envVar] = $_SERVER[$envVar] ?? null;
            }
            $_SERVER[$envVar] = $value;
            $_ENV[$envVar] = $value;
        }
    }

    /**
     * @after
     */
    public function resetEnvVars(): void
    {
        if ($this->originalEnvVars) {
            foreach ($this->originalEnvVars as $envVar => $value) {
                $_SERVER[$envVar] = $value;
                $_ENV[$envVar] = $value;
            }

            $this->originalEnvVars = [];
        }
    }
}
