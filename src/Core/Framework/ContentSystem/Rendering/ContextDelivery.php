<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * What one child receives from one round of {@see ContextDistributor::distribute()}, and the pair of
 * arguments {@see RenderedElementFactory::create()} takes as its `$deliveredContext` and
 * `$distributionReferencedKeys`.
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
 * @internal
 */
#[Package('framework')]
final readonly class ContextDelivery
{
    /**
     * @param array<string, mixed> $context keyed by the key the value was delivered under
     * @param list<string> $distributionReferencedKeys stored keys a distribution config named
     */
    public function __construct(
        public string $elementId,
        public array $context = [],
        public array $distributionReferencedKeys = [],
    ) {
    }

    /**
     * True when this child received nothing at all, which is the ordinary case for a child that consumes no
     * context or matched no provider. {@see StoredElement}s that deliver nothing still get a delivery, so
     * the result stays positionally aligned with the children that produced it.
     */
    public function isEmpty(): bool
    {
        return $this->context === [] && $this->distributionReferencedKeys === [];
    }
}
