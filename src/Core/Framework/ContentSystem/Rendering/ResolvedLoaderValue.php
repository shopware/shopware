<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\Log\Package;

/**
 * What one data requirement resolved to: the loader's value and the identity that value dedups by.
 *
 * The two travel as one type rather than as two parallel maps because they are only meaningful together. The
 * identity describes THIS value at the moment the loader returned it — its `producedFingerprint` is taken
 * here — so a pair that came apart in transit would let the index judge one value by another's identity.
 *
 * An identity is present even when the loader returned no data: such a result is a resolution that ran. The
 * null it resolved takes the shared null ref instead of deduping by that identity, and
 * {@see RenderedElementFactory} omits it where its key is a mapping fallback destination, which leaves the
 * authored fallback on the element.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedLoaderValue
{
    public function __construct(
        public mixed $value,
        public LoaderValueIdentity $identity,
    ) {
    }
}
