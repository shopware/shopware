<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6008', 'FEATURE_NEXT_6008');

    function next6008(): bool
    {
        return FeatureConfig::isActive('next6008');
    }

    function ifNext6008(\Closure $closure): void
    {
        next6008() && $closure();
    }

    function ifNext6008Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6008(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6008(TestCase $test): void
    {
        if (next6008()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6008"');
    }
}
