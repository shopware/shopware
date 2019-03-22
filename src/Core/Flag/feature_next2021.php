<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next2021', 'FEATURE_NEXT2021');

    function next2021(): bool
    {
        return FeatureConfig::isActive('next2021');
    }

    function ifNext2021(\Closure $closure): void
    {
        next2021() && $closure();
    }

    function ifNext2021Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext2021(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext2021(TestCase $test): void
    {
        if (next2021()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next2021"');
    }
}
