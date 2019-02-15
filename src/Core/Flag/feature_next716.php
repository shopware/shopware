<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next716', 'FEATURE_NEXT_716');

    function next716(): bool
    {
        return FeatureConfig::isActive('next716');
    }

    function ifNext716(Closure $closure): void
    {
        next716() && $closure();
    }

    function ifNext716Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext716(Closure::bind($closure, $object, $object));
    }

    function skipTestNext716(TestCase $test): void
    {
        if (next716()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-716"');
    }
}
