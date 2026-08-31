<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Identifies one rendered property value well enough to tell "still the value the loader returned" from
 * "something replaced it": an object by its instance, anything else by a hash of it.
 *
 * It is a collaborator rather than a private method on either side because the rule has two callers at two
 * different times, and the comparison between them is only meaningful while both apply the identical rule. The
 * producer records a fingerprint at lowering time, from the value the loader returned, into
 * {@see LoaderValueIdentity::$producedFingerprint}; {@see ResolvedValueIndexFactory} recomputes one at
 * finalization, from the finished value, and compares the two. A second copy of the rule is therefore not a
 * duplication a reader can tidy up later — it is the gate silently ceasing to work.
 *
 * Both directions of that failure are silent. A producer that hashed objects instead of using `spl_object_id`
 * would disable the gate permissively: every object value would read as replaced, because a hash of an object
 * cannot match the instance id the factory computes, so loader dedup would never fire at all. A producer that
 * fingerprinted the FINISHED value rather than the PRODUCED one would disable it the other way: a listener's
 * replacement would always match itself and so always read as genuine loader output, and the identity would go
 * on governing a value it no longer describes.
 *
 * The object case borrows `spl_object_id`, which PHP reuses after an object is freed. A listener that drops the
 * loader's value and allocates a replacement landing on the recycled id would read as genuine loader output.
 * Nothing cheaper distinguishes an instance, and the consequence is bounded: a shared ref between values that
 * are identity-equal as far as the index can tell, never a wrong value served under a key.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ValueFingerprinter
{
    public function fingerprint(mixed $value): string
    {
        if (\is_object($value)) {
            return (string) spl_object_id($value);
        }

        return Hasher::hash($value);
    }
}
