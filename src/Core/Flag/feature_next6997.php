<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6997', 'FEATURE_NEXT_6997');

    function next6997(): bool
    {
        return FeatureConfig::isActive('next6997');
    }

    function ifNext6997(\Closure $closure): void
    {
        next6997() && $closure();
    }

    function ifNext6997Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6997(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6997(TestCase $test): void
    {
        if (next6997()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-6997"');
    }
}
