<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next717', 'FEATURE_NEXT_717');

    function next717(): bool
    {
        return FeatureConfig::isActive('next717');
    }

    function ifNext717(\Closure $closure): void
    {
        next717() && $closure();
    }

    function ifNext717Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext717(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext717(\PHPUnit\Framework\TestCase $test): void
    {
        if (next717()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-717"');
    }
}
