<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next742', 'FEATURE_NEXT_742');

    function next742(): bool
    {
        return FeatureConfig::isActive('next742');
    }

    function ifNext742(\Closure $closure): void
    {
        next742() && $closure();
    }

    function ifNext742Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext742(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext742(TestCase $test): void
    {
        if (next742()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-742"');
    }
}
