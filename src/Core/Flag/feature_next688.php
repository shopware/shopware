<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next688', 'FEATURE_NEXT_688');

    function next688(): bool
    {
        return FeatureConfig::isActive('next688');
    }

    function ifNext688(\Closure $closure): void
    {
        next688() && $closure();
    }

    function ifNext688Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext688(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext688(\PHPUnit\Framework\TestCase $test): void
    {
        if (next688()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-688"');
    }
}
