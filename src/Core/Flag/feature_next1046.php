<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1046', 'FEATURE_NEXT1046');

    function next1046(): bool
    {
        return FeatureConfig::isActive('next1046');
    }

    function ifNext1046(\Closure $closure): void
    {
        next1046() && $closure();
    }

    function ifNext1046Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1046(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1046(TestCase $test): void
    {
        if (next1046()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next1046"');
    }
}
