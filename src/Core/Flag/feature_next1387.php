<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1387', 'FEATURE_NEXT_1387');

    function next1387(): bool
    {
        return FeatureConfig::isActive('next1387');
    }

    function ifNext1387(\Closure $closure): void
    {
        next1387() && $closure();
    }

    function ifNext1387Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext1387(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1387(\PHPUnit\Framework\TestCase $test): void
    {
        if (next1387()) {
            return;
        }

        $test->markTestSkipped('Skipping feature test "next-1387"');
    }
}
