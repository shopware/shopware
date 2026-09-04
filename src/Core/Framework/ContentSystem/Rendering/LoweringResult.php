<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * What lowering a stored forest produced: the rendered forest, and what the mint recorded about every property
 * key in it.
 *
 * The provenance map is keyed by element id rather than carried on the elements, because it is not part of the
 * rendered model: a response body never shows it, Twig never reads it, and a finalization listener replacing
 * the forest neither has to supply it nor can invalidate the entries for elements it kept. It is derived
 * facts one stage records for a later stage to read instead of re-deriving — the reason the finishing steps
 * can drop nodes and the index can still file every key that survived.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LoweringResult
{
    /**
     * @param list<RenderedElement> $tree
     * @param array<string, array<string, ValueProvenance>> $provenance element id => property key => provenance
     */
    public function __construct(
        public array $tree,
        public array $provenance,
    ) {
    }
}
