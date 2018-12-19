<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next719', 'FEATURE_NEXT719');

    function next719(): bool
    {
        return FeatureConfig::isActive('next719');
    }

    function ifNext719(\Closure $closure): void
    {
        next719() && $closure();
    }

    function ifNext719Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext719(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext719(\PHPUnit\Framework\TestCase $test): void
    {
        if (next719()) {
            return;
        }

        $test->markTestSkipped('Skipping feature test "next719"');
    }
}
