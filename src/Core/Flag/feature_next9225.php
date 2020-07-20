<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next9225', 'FEATURE_NEXT_9225');

    function next9225(): bool
    {
        return FeatureConfig::isActive('next9225');
    }

    function ifNext9225(\Closure $closure): void
    {
        next9225() && $closure();
    }

    function ifNext9225Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext9225(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext9225(TestCase $test): void
    {
        if (next9225()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-9225"');
    }
}
