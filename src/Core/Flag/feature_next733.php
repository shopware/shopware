<?php declare(strict_types=1);

/**
 * Enables features for import export
 */

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next733', 'FEATURE_NEXT_733');

    function next733(): bool
    {
        return FeatureConfig::isActive('next733');
    }

    function ifNext733(\Closure $closure): void
    {
        next733() && $closure();
    }

    function ifNext733Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext733(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext733(TestCase $test): void
    {
        if (next733()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-733"');
    }
}
