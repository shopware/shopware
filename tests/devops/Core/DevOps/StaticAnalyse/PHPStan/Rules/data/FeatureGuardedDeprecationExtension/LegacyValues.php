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
class LegacyValues extends \RuntimeException
{
    /**
     * @deprecated tag:v6.8.0 - Use the replacement.
     */
    public const VALUE = 1;

    /**
     * @deprecated tag:v6.8.0 - Use the replacement.
     */
    public int $value = 1;
}

function guardedValues(callable $action, object $value, LegacyContract $contract, CurrentValues $current): array
{
    if (!Feature::isActive('v6.8.0.0')) {
        try {
            $action();

            return [
                $contract->read(),
                LegacyValues::class,
                LegacyValues::VALUE,
                LegacyContract::VALUE,
                $value instanceof LegacyValues ? $value->value : null,
                CurrentValues::LEGACY,
                $current->legacy,
            ];
        } catch (LegacyValues) {
            return [];
        }
    }

    try {
        $action();

        return [
            $contract->read(),
            LegacyValues::class,
            LegacyValues::VALUE,
            LegacyContract::VALUE,
            $value instanceof LegacyValues ? $value->value : null,
            CurrentValues::LEGACY,
            $current->legacy,
        ];
    } catch (LegacyValues) {
        return [];
    }
}
