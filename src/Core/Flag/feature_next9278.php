<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next9278', 'FEATURE_NEXT_9278');

    function next9278(): bool
    {
        return FeatureConfig::isActive('next9278');
    }

    function ifNext9278(\Closure $closure): void
    {
        next9278() && $closure();
    }

    function ifNext9278Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext9278(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext9278(TestCase $test): void
    {
        if (next9278()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-9278"');
    }
}
