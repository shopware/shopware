import type { ContentElementContextConsumer, ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';

/**
 * A mapping is always read off the layout's root entity, never off an ancestor element.
 */
const MAPPING_SCOPE = 'root';

/**
 * Decides whether a stored consumer is a data mapping, mirroring the server's `StoredMappingValidator`.
 *
 * Scope and `propertyAlias` alone are not enough: the mutation layer writes that same shape for a reference
 * property it resolved against the layout's root-ambient context — a product listing receives the page's
 * listing as a root-scoped consumer aliased onto its `listing` property. Treating that as a mapping would
 * offer the author an unmap button that destroys wiring they never created. A mapping reads a PATH INTO an
 * ambient value and so always carries a dot; an ambient context key never does.
 *
 * @private
 * @sw-package discovery
 */
export function isMappingConsumer(path: string, consumer: ContentElementContextConsumer | undefined): boolean {
    return consumer?.scope === MAPPING_SCOPE && typeof consumer?.propertyAlias === 'string' && path.includes('.');
}

const PRIMITIVE_TYPES = [
    'string',
    'integer',
    'number',
    'boolean',
];

const UNCONSTRAINED_OBJECT_TYPE = 'object';

/**
 * @private
 * @sw-package discovery
 */
export interface PropertyMapping {
    path: string;
    consumer: ContentElementContextConsumer;
}

/**
 * @private
 * @sw-package discovery
 */
export function isMappableProperty(property: ContentSystemElementTypeProperty): boolean {
    return property.mappable === true;
}

/**
 * Finds the mapping that currently feeds `propertyKey`, or null when the property carries a static value.
 *
 * @private
 * @sw-package discovery
 */
export function findPropertyMapping(element: ContentElementNode | null, propertyKey: string): PropertyMapping | null {
    for (const [
        path,
        consumer,
    ] of Object.entries(element?.acceptsContext ?? {})) {
        if (isMappingConsumer(path, consumer) && consumer.propertyAlias === propertyKey) {
            return {
                path,
                consumer,
            };
        }
    }

    return null;
}

/**
 * Narrows the catalogue to the candidates whose value can fill `property`.
 *
 * Two filters, because neither alone is enough. `contextTypes` comes from the server and separates a
 * collection property from a single one — the Administration cannot derive that itself, since telling
 * `MediaCollection` from `MediaEntity` means resolving a PHP class hierarchy in the browser. Skipping it
 * offers a category's single image for the gallery, and picking that yields an element that renders nothing.
 *
 * Past that the class check stays deliberately loose: any non-primitive candidate fits any class-typed
 * property, which over-offers rather than hiding a valid choice. The server's write gate is authoritative
 * and rejects what it cannot satisfy — but note it runs on the layout write, not on the studio's draft
 * path, so an over-offer here is a pick that goes quiet until save rather than one that is refused.
 *
 * @private
 * @sw-package discovery
 */
export function getCandidatesForProperty(
    candidates: ContentSystemMappingCandidate[],
    property: ContentSystemElementTypeProperty,
): ContentSystemMappingCandidate[] {
    const declaredTypes = Array.isArray(property.type) ? property.type : [property.type];
    const contextTypes = property.contextTypes ?? [];

    return candidates.filter(
        (candidate) =>
            contextTypes.includes(candidate.contextType) &&
            declaredTypes.some((declaredType) => permitsCandidate(declaredType, candidate.valueType)),
    );
}

/**
 * Groups candidates by their catalogue group, preserving the order the catalogue returned them in.
 *
 * @private
 * @sw-package discovery
 */
export function groupCandidates(
    candidates: ContentSystemMappingCandidate[],
): Array<{ group: string; candidates: ContentSystemMappingCandidate[] }> {
    const groups = new Map<string, ContentSystemMappingCandidate[]>();

    for (const candidate of candidates) {
        const group = groups.get(candidate.group);

        if (group) {
            group.push(candidate);
            continue;
        }

        groups.set(candidate.group, [candidate]);
    }

    return Array.from(groups.entries()).map(([
        group,
        groupCandidateList,
    ]) => ({
        group,
        candidates: groupCandidateList,
    }));
}

function permitsCandidate(declaredType: string, candidateValueType: string): boolean {
    if (declaredType === UNCONSTRAINED_OBJECT_TYPE) {
        return !isPrimitive(candidateValueType);
    }

    if (isPrimitive(declaredType)) {
        return declaredType === candidateValueType;
    }

    return !isPrimitive(candidateValueType);
}

function isPrimitive(type: string): boolean {
    return PRIMITIVE_TYPES.includes(type);
}
