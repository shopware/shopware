<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next516', 'FEATURE_NEXT_516');

    function next516(): bool
    {
        return FeatureConfig::isActive('next516');
    }

    function ifNext516(Closure $closure): void
    {
        next516() && $closure();
    }

    function ifNext516Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext516(Closure::bind($closure, $object, $object));
    }

    function skipTestNext516(TestCase $test): void
    {
        if (next516()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "next-516"');
    }
}
