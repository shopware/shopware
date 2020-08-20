<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next9279', 'FEATURE_NEXT_9279');

    function next9279(): bool
    {
        return FeatureConfig::isActive('next9279');
    }

    function ifNext9279(\Closure $closure): void
    {
        next9279() && $closure();
    }

    function ifNext9279Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext9279(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext9279(TestCase $test): void
    {
        if (next9279()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-9279"');
    }
}
