<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\Log\Package;

/**
 * Round-trip contract: for any wire form $x this serializer accepts, `encode(decode($x))` must be
 * stable (idempotent on the wire form) and equal to `decode($x)->jsonSerialize()` — `decode()` must
 * not normalize or coerce values, and `encode()` must not diverge from the config's `jsonSerialize()`.
 * {@see AttributionReconciler} decides whether a stored
 * attribution is still honest by comparing the element's wiring against the specification's binding
 * via their canonicalized `encode(decode(...))`, so a serializer that violates this contract makes two
 * configs that carry the same authored wiring encode differently and drops an attribution that is in
 * fact still honest.
 */
#[Package('framework')]
abstract class AbstractContentDataLoaderConfigSerializer
{
    /**
     * @return non-empty-string
     */
    abstract public static function getSource(): string;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function decode(array $data): AbstractContentDataLoaderConfig;

    /**
     * @return array<string, mixed>
     */
    abstract public function encode(AbstractContentDataLoaderConfig $config): array;
}
