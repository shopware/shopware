<?php declare(strict_types=1);

namespace Flag {
    use Closure;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next754', 'FEATURE_NEXT_754');

    function next754(): bool
    {
        return FeatureConfig::isActive('next754');
    }

    function ifNext754(Closure $closure): void
    {
        next754() && $closure();
    }

    function ifNext754Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifNext754(Closure::bind($closure, $object, $object));
    }

    function skipTestNext754(TestCase $test): void
    {
        if (next754()) {
            return;
        }

        $test::markTestSkipped('Skipping feature test "NEXT-754"');
    }
}
