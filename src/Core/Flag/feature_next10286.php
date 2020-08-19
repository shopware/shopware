<?php declare(strict_types=1);

namespace Flag {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next10286', 'FEATURE_NEXT_10286');

    function next10286(): bool
    {
        return FeatureConfig::isActive('next10286');
    }

    function ifNext10286(\Closure $closure): void
    {
        next10286() && $closure();
    }

    function ifNext10286Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments): void {
            $this->{$methodName}(...$arguments);
        };

        ifNext10286(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext10286(TestCase $test): void
    {
        if (next10286()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-10286"');
    }
}
