<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag\_fixture {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('nextFix102', 'FEATURE_NEXT_FIX_102');

    function nextFix102(): bool
    {
        return FeatureConfig::isActive('nextFix102');
    }

    function ifNextFix102(\Closure $closure): void
    {
        nextFix102() && $closure();
    }

    function ifNextFix102Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNextFix102(\Closure::bind($closure, $object, $object));
    }

    function skipTestNextFix102(TestCase $test): void
    {
        if (nextFix102()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-fix-102"');
    }
}
