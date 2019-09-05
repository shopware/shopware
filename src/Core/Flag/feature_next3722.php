<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next3722', 'FEATURE_NEXT_3722');

    function next3722(): bool
    {
        return FeatureConfig::isActive('next3722');
    }

    function ifNext3722(\Closure $closure): void
    {
        next3722() && $closure();
    }

    function ifNext3722Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext3722(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext3722(TestCase $test): void
    {
        if (next3722()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next3722"');
    }
}
