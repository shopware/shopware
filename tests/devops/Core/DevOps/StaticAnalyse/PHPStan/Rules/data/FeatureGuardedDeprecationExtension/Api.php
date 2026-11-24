<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FeatureGuardedDeprecationExtension;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Api
{
    /**
     * @deprecated tag:v6.8.0 - Use the replacement.
     */
    public function old(): void
    {
    }

    /**
     * @deprecated tag:v6.9.0 - Use the replacement.
     */
    public function future(): void
    {
    }

    public function lookup(): void
    {
    }
}

function calls(Api $api, bool $enabled, string $dynamicFlag): void
{
    if (!Feature::isActive('v6.8.0.0')) {
        $api->lookup();
        $api->old();
        $api->future();
    }
    if (Feature::isActive('v6.8.0.0')) {
        $api->old();
    }
    $api->old();
    if (!Feature::isActive('v6.9.0.0')) {
        $api->old();
    }
    if (!Feature::isActive('TWIGSTAN_LEGACY_TEST')) {
        $api->old();
    }
    if (!Feature::isActive('TWIGSTAN_UNKNOWN_TEST')) {
        $api->old();
    }
    $context = [];
    $context['useNew'] = Feature::isActive('v6.8.0.0') || Feature::isActive('TWIGSTAN_LEGACY_TEST');
    if (!$context['useNew']) {
        $api->lookup();
        $api->old();
    }
    if ($enabled) {
        if (!Feature::isActive('v6.8.0.0')) {
            $api->lookup();
        }
    }
    $api->old();
    if (!Feature::isActive($dynamicFlag)) {
        $api->old();
    }
}
