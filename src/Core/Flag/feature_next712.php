<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next712', 'FEATURE_NEXT712');

    function next712(): bool
    {
        return FeatureConfig::isActive('next712');
    }

    function ifNext712(Closure $closure): void
    {
        next712() && $closure();
    }

    function ifNext712Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext712(Closure::bind($closure, $object, $object));
    }

    function skipTestNext712(TestCase $test): void
    {
        if (next712()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next712"');
    }
}
