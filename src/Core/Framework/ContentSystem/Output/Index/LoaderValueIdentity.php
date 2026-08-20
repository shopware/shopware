<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\Log\Package;

/**
 * The four components of a loader-resolved value's dedup key:
 *
 * - `source` is the requirement's loader source
 * - `configHash` hashes the canonicalized encoded config
 * - `inputsHash` hashes the canonical resolved inputs
 * - `producedFingerprint` identifies the value the loader returned
 *
 * The source is a component of its own rather than something a hash is trusted to carry. The extraction path
 * this type replaces hashed the encoded config alone, so two loaders whose configs encoded identically produced
 * the same hash under different sources; making the source explicit means a dedup key can never merge two
 * sources by accident.
 *
 * `inputsHash` is load-bearing rather than decorative, and it is the component that fixes the defect in that
 * same path: it is the only thing that can make two distinct-but-equal non-entity loader values — two
 * separately loaded collections, trees or listing results, which two loads always are, because the DAL keeps no
 * identity map — share one ref. Instance identity cannot, and value comparison is not consulted for a
 * loader-resolved value at all. Drop it from the key and two requirements differing only in their inputs
 * collapse onto one ref.
 *
 * `producedFingerprint` is recorded by the producer at lowering time, from the value the loader returned, and
 * is NOT recomputed at finalize — that is the whole point of it. {@see ResolvedValueIndexFactory} recomputes the
 * *finished* value's fingerprint and compares the two, which is how a value a finalization listener replaced is
 * told apart from the loader's own output. Both sides get the rule from {@see ValueFingerprinter}, which exists
 * so that there is one rule rather than two that must be kept in step; its docblock has the two ways a second
 * copy would break the comparison silently.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LoaderValueIdentity
{
    public function __construct(
        public string $source,
        public string $configHash,
        public string $inputsHash,
        public string $producedFingerprint,
    ) {
    }
}
