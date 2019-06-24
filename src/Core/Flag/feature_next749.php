<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next749', 'FEATURE_NEXT_749');

    function next749(): bool
    {
        return FeatureConfig::isActive('next749');
    }

    function ifNext749(\Closure $closure): void
    {
        next749() && $closure();
    }

    function ifNext749Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext749(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext749(TestCase $test): void
    {
        if (next749()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT_749"');
    }
}
