<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next739', 'FEATURE_NEXT_739');

    function next739(): bool
    {
        return FeatureConfig::isActive('next739');
    }

    function ifNext739(Closure $closure): void
    {
        next739() && $closure();
    }

    function ifNext739Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext739(Closure::bind($closure, $object, $object));
    }

    function skipTestNext739(TestCase $test): void
    {
        if (next739()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-739"');
    }
}
