<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag\_fixture {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('nextFix101', 'FEATURE_NEXT_FIX_101');

    function nextFix101(): bool
    {
        return FeatureConfig::isActive('nextFix101');
    }

    function ifNextFix101(\Closure $closure): void
    {
        nextFix101() && $closure();
    }

    function ifNextFix101Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNextFix101(\Closure::bind($closure, $object, $object));
    }

    function skipTestNextFix101(TestCase $test): void
    {
        if (nextFix101()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-fix-101"');
    }
}
