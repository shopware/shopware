<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FeatureGuardedDeprecationExtension;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Use the replacement.
 */
#[Package('framework')]
interface LegacyContract
{
    public const VALUE = 1;

    public function read(): int;
}
