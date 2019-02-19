<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1309', 'FEATURE_NEXT_1309');

    function next1309(): bool
    {
        return FeatureConfig::isActive('next1309');
    }

    function ifNext1309(Closure $closure): void
    {
        next1309() && $closure();
    }

    function ifNext1309Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1309(Closure::bind($closure, $object, $object));
    }

    function skipTestNext1309(TestCase $test): void
    {
        if (next1309()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-1309"');
    }
}
