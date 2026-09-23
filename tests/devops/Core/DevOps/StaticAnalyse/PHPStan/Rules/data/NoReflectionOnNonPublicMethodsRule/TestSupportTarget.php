<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReflectionOnNonPublicMethodsRule;

/**
 * Stand-in for a test-support class, which is not production API.
 *
 * @internal
 */
class TestSupportTarget
{
    public function touch(): void
    {
        $this->helperInternal();
    }

    private function helperInternal(): void
    {
    }
}
