<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * Canonicalizes an encoded data-loader config array into a stable shape for structural comparison: key-sorts
 * every map level and value-sorts every list level (e.g. an `associations` list), so two configs that differ
 * only in key or list order compare equal.
 *
 * Shared by {@see PropertiesExtractionVisitor} (dedup hash) and {@see AttributionReconciler} (honesty check).
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ConfigCanonicalizer
{
    /**
     * @param array<int|string, mixed> $config
     *
     * @return array<int|string, mixed>
     */
    public function canonicalize(array $config): array
    {
        ksort($config);

        foreach ($config as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                sort($value);
                $config[$key] = $value;

                continue;
            }

            $config[$key] = $this->canonicalize($value);
        }

        return $config;
    }
}
