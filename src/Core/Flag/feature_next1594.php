<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next1594', 'FEATURE_NEXT_1594');

    function next1594(): bool
    {
        return FeatureConfig::isActive('next1594');
    }

    function ifNext1594(Closure $closure): void
    {
        next1594() && $closure();
    }

    function ifNext1594Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext1594(Closure::bind($closure, $object, $object));
    }

    function skipTestNext1594(TestCase $test): void
    {
        if (next1594()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next_1594"');
    }
}
