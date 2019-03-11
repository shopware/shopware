<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next681', 'FEATURE_NEXT_681');

    function next681(): bool
    {
        return FeatureConfig::isActive('next681');
    }

    function ifNext681(\Closure $closure): void
    {
        next681() && $closure();
    }

    function ifNext681Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext681(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext681(TestCase $test): void
    {
        if (next681()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-681"');
    }
}
