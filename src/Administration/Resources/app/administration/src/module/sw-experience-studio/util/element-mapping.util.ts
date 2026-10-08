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
export function isMappingConsumer(
    consumer: ContentElementContextConsumer | undefined,
): consumer is ContentElementContextConsumer & { source: NonNullable<ContentElementContextConsumer['source']> } {
    return typeof consumer?.source === 'object' && consumer.source !== null;
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
 * itself rather than a typed slot. Non-root sources are allowed too: the server resolves them through their source
 * provider, while `valueType` ensures the value has a text form after any projection.
 *
 * @private
 * @sw-package discovery
 */
export function getInlineMappingCandidates(candidates: ContentSystemMappingCandidate[]): ContentSystemMappingCandidate[] {
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

    return (
        [
            translations[currentLocale],
            translations[fallbackLocale],
            ...Object.values(translations),
        ].find((translation) => typeof translation === 'string' && translation !== '') ?? ''
    );
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
                path:
                    consumer.source.type === 'root'
                        ? [
                              consumer.source.id,
                              consumer.source.path,
                          ]
                              .filter(Boolean)
                              .join('.')
                        : `${consumer.source.type}:${consumer.source.id}${consumer.source.path ? `.${consumer.source.path}` : ''}`,
                consumer,
            };
        }
    }

    return null;
}

/**
 * Narrows the catalogue to the candidates whose value can fill `property`.
 *
 * `contextTypes` separates collection properties from single-value properties. `compatibleTypes` mirrors the
 * server's PHP assignability check, so class candidates only appear for properties that can accept their value.
 * The Administration cannot derive either distinction from class names alone.
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
            declaredTypes.some((declaredType) => permitsCandidate(declaredType, candidate)),
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

    return Array.from(groups.entries()).map(
        ([
            group,
            groupCandidateList,
        ]) => ({
            group,
            candidates: groupCandidateList,
        }),
    );
}

function permitsCandidate(declaredType: string, candidate: ContentSystemMappingCandidate): boolean {
    const candidateValueType = candidate.valueType;

    if (declaredType === UNCONSTRAINED_OBJECT_TYPE) {
        return !isPrimitive(candidateValueType);
    }

    if (isPrimitive(declaredType)) {
        return declaredType === candidateValueType;
    }

    return candidate.compatibleTypes?.includes(declaredType) ?? declaredType === candidateValueType;
}

function isPrimitive(type: string): boolean {
    return PRIMITIVE_TYPES.includes(type);
}
