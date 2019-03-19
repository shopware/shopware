<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1223', 'FEATURE_NEXT_1223');

    function next1223(): bool
    {
        return FeatureConfig::isActive('next1223');
    }

    function ifNext1223(\Closure $closure): void
    {
        next1223() && $closure();
    }

    function ifNext1223Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1223(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1223(TestCase $test): void
    {
        if (next1223()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-1223"');
    }
}
