<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next7399', 'FEATURE_NEXT_7399');

    function next7399(): bool
    {
        return FeatureConfig::isActive('next7399');
    }

    function ifNext7399(\Closure $closure): void
    {
        next7399() && $closure();
    }

    function ifNext7399Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext7399(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext7399(TestCase $test): void
    {
        if (next7399()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-7399"');
    }
}
