<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature as Alias;

const THREE_PART_FEATURE = 'v6.9.0';

function checkFeatureFlags(): void
{
    Feature::isActive('v6.8.0');
    Alias::triggerDeprecationOrThrow('v6.8.0', 'Deprecated');
    Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Deprecated', silentUntil: 'v6.7.0');
    Feature::isActive(feature: 'v6.8.0');
    Feature::has(THREE_PART_FEATURE);
    Feature::withFeatureDisabled('V6_8_0', static fn (): null => null);
    Feature::isActive('v6');
    Feature::isActive('v6.8');
    Feature::isActive('v6.8.0.0.1');
    Feature::isActive('v6.8.x.0');
    Feature::isActive("v6.8.0.0\n");

    Feature::isActive('v6.8.0.0');
    Feature::isActive('V6_8_0_0');
    Feature::isActive('MY_FEATURE');
    Feature::deprecatedMethodMessage('Class', 'method', 'v6.8.0');
}
