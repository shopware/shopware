<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1567', 'FEATURE_NEXT_1567');

    function next1567(): bool
    {
        return FeatureConfig::isActive('next1567');
    }

    function ifNext1567(\Closure $closure): void
    {
        next1567() && $closure();
    }

    function ifNext1567Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext1567(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext1567(\PHPUnit\Framework\TestCase $test): void
    {
        if (next1567()) {
            return;
        }

        $test->markTestSkipped('Skipping feature test "next-1567"');
    }
}
