<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next685', 'FEATURE_NEXT_685');

    function next685(): bool
    {
        return FeatureConfig::isActive('next685');
    }

    function ifNext685(\Closure $closure): void
    {
        next685() && $closure();
    }

    function ifNext685Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext685(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext685(TestCase $test): void
    {
        if (next685()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-685"');
    }
}
