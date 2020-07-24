<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next6059', 'FEATURE_NEXT_6059');

    function next6059(): bool
    {
        return FeatureConfig::isActive('next6059');
    }

    function ifNext6059(\Closure $closure): void
    {
        next6059() && $closure();
    }

    function ifNext6059Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext6059(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext6059(TestCase $test): void
    {
        if (next6059()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next_6059"');
    }
}
