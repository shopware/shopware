<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FeatureGuardedDeprecationExtension;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class InactiveCallbacks
{
    public function run(Api $api): void
    {
        Feature::callSilentIfInactive('v6.8.0.0', static function () use ($api): void {
            $api->old();
        });
        Feature::ifNotActive('v6.8.0.0', static function () use ($api): void {
            $api->old();
        });
        Feature::ifActive('v6.8.0.0', static function () use ($api): void {
            $api->old();
        });
        Feature::silent('v6.8.0.0', static function () use ($api): void {
            $api->old();
        });
    }
}
