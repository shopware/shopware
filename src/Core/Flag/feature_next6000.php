<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6000', 'FEATURE_NEXT_6000');

    function next6000(): bool
    {
        return FeatureConfig::isActive('next6000');
    }

    function ifNext6000(\Closure $closure): void
    {
        next6000() && $closure();
    }

    function ifNext6000Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6000(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6000(TestCase $test): void
    {
        if (next6000()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6000"');
    }
}
