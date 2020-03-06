<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6050', 'FEATURE_NEXT_6050');

    function next6050(): bool
    {
        return FeatureConfig::isActive('next6050');
    }

    function ifNext6050(\Closure $closure): void
    {
        next6050() && $closure();
    }

    function ifNext6050Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6050(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6050(TestCase $test): void
    {
        if (next6050()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6050"');
    }
}
