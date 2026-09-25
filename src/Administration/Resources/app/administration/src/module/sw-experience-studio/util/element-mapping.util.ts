import type { ContentElementContextConsumer, ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';

/**
 * Decides whether a stored consumer is a data mapping, mirroring the server's `Mapping/MappingConsumers`.
 *
 * Only data mappings carry an explicit source path; ordinary and server-mirrored context wiring do not.
 *
 * @private
 * @sw-package discovery
 */
export function isMappingConsumer(consumer: ContentElementContextConsumer | undefined): boolean {
    return typeof consumer?.sourcePath === 'string';
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
 * Whether the author may put `{{map:path}}` tokens in this property's text.
 *
 * The server enforces that `mappable` and `inlineMappable` are never both set on one property, so the two callers
 * never have to agree on a tie-break.
 *
 * @private
 * @sw-package discovery
 */
export function isInlineMappableProperty(property: ContentSystemElementTypeProperty): boolean {
    return property.inlineMappable === true;
}

/**
 * Narrows the catalogue to the candidates that have a text form.
 *
 * This mirrors `InlineMappingInterpolator::STRINGIFIABLE_TYPES`, which is `PropertyType::PRIMITIVE_TYPES`. An entity
 * or a collection cannot be written into a sentence, and the server refuses such a token outright rather than
 * rendering something apologetic, so offering one here would only lead the author into a validation error.
 *
 * Unlike `getCandidatesForProperty` there is no `contextTypes` filter, because the property being filled is the text
 * itself rather than a typed slot: `valueType` already carries the answer, and it is the effective type after any
 * projection, so a candidate whose projection formats an entity as a string is correctly offered.
 *
 * @private
 * @sw-package discovery
 */
export function getInlineMappingCandidates(
    candidates: ContentSystemMappingCandidate[],
): ContentSystemMappingCandidate[] {
    return candidates.filter((candidate) => isPrimitive(candidate.valueType));
}

/**
 * Resolves literal translations carried by dynamic catalogue entries such as custom fields.
 *
 * @private
 * @sw-package discovery
 */
export function getMappingCandidateTranslation(
    translations: Record<string, string> | undefined,
    currentLocale: string,
    fallbackLocale: string,
): string {
    if (!translations) {
        return '';
    }

    return [
        translations[currentLocale],
        translations[fallbackLocale],
        ...Object.values(translations),
    ].find((translation) => typeof translation === 'string' && translation !== '') ?? '';
}

/**
 * Finds the mapping that currently feeds `propertyKey`, or null when the property carries a static value.
 *
 * @private
 * @sw-package discovery
 */
export function findPropertyMapping(element: ContentElementNode | null, propertyKey: string): PropertyMapping | null {
    for (const [
        targetProperty,
        consumer,
    ] of Object.entries(element?.acceptsContext ?? {})) {
        if (targetProperty === propertyKey && isMappingConsumer(consumer)) {
            return {
                path: consumer.sourcePath as string,
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
