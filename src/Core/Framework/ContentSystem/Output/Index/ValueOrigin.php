<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * Where one rendered property key's value came from. The cases are declared in the order
 * {@see ResolvedValueIndexFactory::emissionOrder()} hands a key its ref in, which is where that order is
 * stated and enforced; this declaration only keeps the two readable side by side.
 *
 * The first four cases are the four members
 * {@see RenderedElementFactory} composes a rendered
 * element's property map from, named after the terms that class states them in, so the two sets can be read
 * against each other without a translation table. The fifth has no counterpart there because it names a key
 * that class never wrote.
 *
 * @internal
 */
#[Package('framework')]
enum ValueOrigin
{
    /**
     * A key the element's type declares as something authored rather than resolved — a primitive, a bare
     * `object`, or any union — carrying the stored value under that key.
     */
    case DeclaredAuthored;

    /**
     * A data-requirement key, carrying the value its loader resolved — including a present null, which is a
     * loader that ran and found nothing.
     */
    case LoaderResolved;

    /**
     * A key context was actually delivered under, carrying the delivered value.
     */
    case DeliveredContext;

    /**
     * A stored key a parent's distribution config dereferences by name, carrying the stored value under it.
     */
    case DistributionReferenced;

    /**
     * A rendered property key with no provenance at all.
     *
     * A listener on {@see RenderedTreeFinalizationEvent} replaces
     * the whole rendered forest, so it may put any key on any element, and the index is built after that event
     * has run. Such a key was never produced by a pipeline tier and nothing recorded a provenance entry for it,
     * yet it is on the element and the response has to carry its value — so it gets a ref like every other key,
     * emitted after the four tiers above.
     */
    case Injected;
}
