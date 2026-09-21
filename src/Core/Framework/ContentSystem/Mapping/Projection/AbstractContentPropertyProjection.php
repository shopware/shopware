<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Projection;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * A named, pure reshaping of a mapped value, applied between the path that resolved it and the property it
 * fills. Tag an implementation `content_system.property_projection` to have it registered.
 *
 * It exists because a dotted path can only walk from one object to the next, and the data an author wants is
 * not always shaped like the property that wants it. A product's images live in `product.media` as a
 * `ProductMediaCollection` — a list of ASSIGNMENT records (position, cover flag) each holding the image one
 * level further in — while the gallery element declares a plain `MediaCollection`. "Take the `media` off
 * every item" is a loop, and a path has no way to say it. That is the gap a projection fills, and the only
 * one: a projection never fetches, filters by context, or decides anything.
 *
 * The author never sees this. {@see MappingCandidate} advertises the EFFECTIVE type — what the property ends
 * up holding — and keeps the projection that gets there to itself, so the selection UI stays a plain type
 * match and the catalogue entry stays one line.
 *
 * Three requirements on an implementation, the first two enforced at build time by
 * `DependencyInjection/CompilerPass/ContentSystemPropertyProjectionCompilerPass` and the third only by review:
 *
 * - `name()` is unique across the container and is what a candidate and a stored consumer refer to.
 * - `inputType()` and `outputType()` are FQCNs or `PropertyType::PRIMITIVE_TYPES` members, and they are
 *   contracts: the registry refuses a value the input type does not admit rather than letting a mismatched
 *   value reach `project()`.
 * - `project()` MUST be pure and cheap. It runs once per mapped property per element per render, and it must
 *   not touch the database, the session, or the sales-channel context — a projection that varied by request
 *   would make a rendered value depend on something the cache layer never sees.
 */
#[Package('framework')]
abstract class AbstractContentPropertyProjection
{
    /**
     * Stable identifier, stored on the element that uses it. Renaming one is a breaking change for every
     * layout already referring to it, so treat it as wire format rather than as an implementation detail.
     */
    abstract public function name(): string;

    /**
     * The type this projection accepts: what actually sits at the candidate's path.
     */
    abstract public function inputType(): string;

    /**
     * The type this projection produces: what the property receives, and what the candidate advertises as its
     * `valueType`.
     */
    abstract public function outputType(): string;

    /**
     * Never called with null — the delivery layer treats an unresolvable path as "deliver nothing" before a
     * projection is reached, so an implementation does not handle the empty case.
     */
    abstract public function project(mixed $value): mixed;
}
