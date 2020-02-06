<?php declare(strict_types=1);

/**
 * Enables features for composer 2.0 usage
 */

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1797', 'FEATURE_NEXT_1797');

    function next1797(): bool
    {
        return FeatureConfig::isActive('next1797');
    }

    function ifNext1797(\Closure $closure): void
    {
        next1797() && $closure();
    }

    function ifNext1797Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext1797(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1797(TestCase $test): void
    {
        if (next1797()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-1797"');
    }
}
