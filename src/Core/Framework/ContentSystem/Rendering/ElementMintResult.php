<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * One minted rendered element together with what the mint recorded about each of its property keys.
 *
 * Returning the element inside a record does not change what {@see RenderedElementFactory} mints — the same
 * reading `StoredTreePreparer` gets for handing its forests back inside a `TreePreparationResult`. The
 * provenance rides along because only the mint knows it: the factory composes the property map from four
 * members in one pass, and which member won a key is visible there and nowhere afterwards.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementMintResult
{
    /**
     * @param array<string, ValueProvenance> $provenance property key => which member produced it
     */
    public function __construct(
        public RenderedElement $element,
        public array $provenance,
    ) {
    }
}
