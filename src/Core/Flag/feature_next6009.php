<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6009', 'FEATURE_NEXT_6009');

    function next6009(): bool
    {
        return FeatureConfig::isActive('next6009');
    }

    function ifNext6009(\Closure $closure): void
    {
        next6009() && $closure();
    }

    function ifNext6009Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6009(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6009(TestCase $test): void
    {
        if (next6009()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6009"');
    }
}
