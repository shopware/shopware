<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1271', 'FEATURE_NEXT_1271');

    function next1271(): bool
    {
        return FeatureConfig::isActive('next1271');
    }

    function ifNext1271(\Closure $closure): void
    {
        next1271() && $closure();
    }

    function ifNext1271Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1271(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1271(TestCase $test): void
    {
        if (next1271()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-1271"');
    }
}
