<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FeatureGuardedDeprecationExtension;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class CurrentValues
{
    /**
     * @deprecated tag:v6.8.0 - Use the replacement.
     */
    public const LEGACY = 1;

    /**
     * @deprecated tag:v6.8.0 - Use the replacement.
     */
    public int $legacy = 1;
}
