<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FeatureGuardedDeprecationExtension;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Use the replacement.
 */
#[Package('framework')]
class LegacyApi
{
    public static function old(): void
    {
    }
}

function staticCallsAndConstruction(): object
{
    if (!Feature::isActive('v6.8.0.0')) {
        LegacyApi::old();

        return new LegacyApi();
    }
    LegacyApi::old();

    return new LegacyApi();
}
