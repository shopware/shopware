<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next2539', 'FEATURE_NEXT_2539');

    function next2539(): bool
    {
        return FeatureConfig::isActive('next2539');
    }

    function ifNext2539(\Closure $closure): void
    {
        next2539() && $closure();
    }

    function ifNext2539Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext2539(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext2539(TestCase $test): void
    {
        if (next2539()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-2539"');
    }
}
