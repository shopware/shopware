<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1207', 'FEATURE_NEXT_1207');

    function next1207(): bool
    {
        return FeatureConfig::isActive('next1207');
    }

    function ifNext1207(\Closure $closure): void
    {
        next1207() && $closure();
    }

    function ifNext1207Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1207(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1207(TestCase $test): void
    {
        if (next1207()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-1207"');
    }
}
