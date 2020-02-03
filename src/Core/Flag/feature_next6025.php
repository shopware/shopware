<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6025', 'FEATURE_NEXT_6025');

    function next6025(): bool
    {
        return FeatureConfig::isActive('next6025');
    }

    function ifNext6025(\Closure $closure): void
    {
        next6025() && $closure();
    }

    function ifNext6025Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6025(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6025(TestCase $test): void
    {
        if (next6025()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT_6025"');
    }
}
