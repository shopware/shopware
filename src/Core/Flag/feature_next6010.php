<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6010', 'FEATURE_NEXT_6010');

    function next6010(): bool
    {
        return FeatureConfig::isActive('next6010');
    }

    function ifNext6010(\Closure $closure): void
    {
        next6010() && $closure();
    }

    function ifNext6010Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6010(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6010(TestCase $test): void
    {
        if (next6010()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6010"');
    }
}
