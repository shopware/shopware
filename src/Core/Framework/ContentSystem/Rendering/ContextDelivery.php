<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * What one child receives from one round of {@see ContextDistributor::distribute()}, plus mapping fallback
 * decisions passed to {@see RenderedElementFactory::create()}.
 *
 * `$context` is keyed by the key each value was DELIVERED under — a consumer's `propertyAlias` when it
 * declares one, otherwise its own consumer key. A key present here holding `null` is a resolution that ran
 * and produced nothing; a key absent is one that was never delivered at all. The factory preserves that
 * distinction, so this object must not collapse it by filling in keys that nothing delivered.
 *
 * `$distributionReferencedKeys` names the stored keys a distribution config dereferenced BY NAME to decide
 * what this child receives — today only {@see KeyedDistributionConfig::$keyProperty}. It carries only the
 * keys that were actually used against this child: a sibling that happens to store the same key but never
 * matched as a consumer contributes nothing here.
 *
 * `$mappingFallbackKeys` names whole-field mappings whose source was missing or empty. Those destinations may
 * use their authored or loader-resolved value; a present `null` is delivered normally and does not appear here.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContextDelivery
{
    /**
     * @param array<string, mixed> $context keyed by the key the value was delivered under
     * @param list<string> $distributionReferencedKeys stored keys a distribution config named
     * @param list<string> $mappingFallbackKeys mapped destinations whose source was missing or empty, so their authored or loader-resolved value may be used
     */
    public function __construct(
        public string $elementId,
        public array $context = [],
        public array $distributionReferencedKeys = [],
        public array $mappingFallbackKeys = [],
    ) {
    }
}
