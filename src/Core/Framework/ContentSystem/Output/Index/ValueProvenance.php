<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * What the pipeline recorded about one rendered property key: which member produced it and, for a
 * loader-resolved one, the identity its value dedups by.
 *
 * A {@see LoaderValueIdentity} is present exactly for {@see ValueOrigin::LoaderResolved} and never otherwise.
 * The constructor enforces both halves of that: an identity on any other origin would be a dedup key nothing
 * consults, and a loader-resolved key without one would silently fall back to plain value dedup and merge two
 * requirements that only happen to have resolved to equal values.
 *
 * Because the two are locked together, {@see ResolvedValueIndexFactory} keys off the identity's presence
 * rather than re-testing the origin.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ValueProvenance
{
    public function __construct(
        public ValueOrigin $origin,
        public ?LoaderValueIdentity $loaderIdentity = null,
    ) {
        $this->rejectIncoherentIdentity();
    }

    private function rejectIncoherentIdentity(): void
    {
        if ($this->origin === ValueOrigin::LoaderResolved && $this->loaderIdentity === null) {
            throw ContentSystemException::invalidMapValue(
                \sprintf('Value provenance (%s)', $this->origin->name),
                'loaderIdentity',
                LoaderValueIdentity::class,
                'null'
            );
        }

        if ($this->origin !== ValueOrigin::LoaderResolved && $this->loaderIdentity !== null) {
            throw ContentSystemException::invalidMapValue(
                \sprintf('Value provenance (%s)', $this->origin->name),
                'loaderIdentity',
                'null',
                LoaderValueIdentity::class
            );
        }
    }
}
