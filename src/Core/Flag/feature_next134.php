<?php declare(strict_types=1);

namespace Flag {
    use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

    FeatureConfig::registerFlag('next134', 'FEATURE_NEXT_134');

    function next134(): bool
    {
        return FeatureConfig::isActive('next134');
    }

    function ifNext134(\Closure $closure): void
    {
        next134() && $closure();
    }

    function ifNext134Call($object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($methodName, $arguments) {
            $this->{$methodName}(...$arguments);
        };

        ifnext134(\Closure::bind($closure, $object, $object));
    }

    function skipTestNext134(\PHPUnit\Framework\TestCase $test): void
    {
        if (next134()) {
            return;
        }

        $test->markTestSkipped('Skipping feature test "NEXT-134"');
    }
}
