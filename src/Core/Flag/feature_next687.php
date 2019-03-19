<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next687', 'FEATURE_NEXT687');

    function next687(): bool
    {
        return FeatureConfig::isActive('next687');
    }

    function ifNext687(\Closure $closure): void
    {
        next687() && $closure();
    }

    function ifNext687Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext687(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext687(TestCase $test): void
    {
        if (next687()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next687"');
    }
}
