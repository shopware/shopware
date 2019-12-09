<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next5515', 'FEATURE_NEXT_5515');

    function next5515(): bool
    {
        return FeatureConfig::isActive('next5515');
    }

    function ifNext5515(\Closure $closure): void
    {
        next5515() && $closure();
    }

    function ifNext5515Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext5515(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext5515(TestCase $test): void
    {
        if (next5515()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-5515"');
    }
}
