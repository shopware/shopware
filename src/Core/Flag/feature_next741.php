<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next741', 'FEATURE_NEXT_741');

    function next741(): bool
    {
        return FeatureConfig::isActive('next741');
    }

    function ifNext741(\Closure $closure): void
    {
        next741() && $closure();
    }

    function ifNext741Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext741(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext741(TestCase $test): void
    {
        if (next741()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT_741"');
    }
}
