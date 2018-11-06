<?php declare(strict_types=1);
    
namespace Shopware\Core\Framework\Test\FeatureFlag\_fixture;

use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

FeatureConfig::addFlag('nextFix101');

function nextFix101(): bool
{
    return FeatureConfig::isActive('nextFix101');
}

function ifNextFix101(\Closure $closure): void
{
    nextFix101() && $closure();
}

function ifNextFix101Call($object, string $methodName): void
{
    $closure = function() use ($methodName) {
        $this->{$methodName}();
    };

    ifnextFix101(\Closure::bind($closure, $object, $object));
}
