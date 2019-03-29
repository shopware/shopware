<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next700', 'FEATURE_NEXT700');

    function next700(): bool
    {
        return FeatureConfig::isActive('next700');
    }

    function ifNext700(\Closure $closure): void
    {
        next700() && $closure();
    }

    function ifNext700Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext700(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext700(TestCase $test): void
    {
        if (next700()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next700"');
    }
}
