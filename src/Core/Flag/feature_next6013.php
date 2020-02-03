<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6013', 'FEATURE_NEXT_6013');

    function next6013(): bool
    {
        return FeatureConfig::isActive('next6013');
    }

    function ifNext6013(\Closure $closure): void
    {
        next6013() && $closure();
    }

    function ifNext6013Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6013(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6013(TestCase $test): void
    {
        if (next6013()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT_6013"');
    }
}
