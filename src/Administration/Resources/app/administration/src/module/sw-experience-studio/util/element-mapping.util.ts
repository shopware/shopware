import type { ContentElementContextConsumer, ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';

/**
 * Decides whether a stored consumer is a data mapping, mirroring the server's `Mapping/MappingConsumers`.
 *
 * Only data mappings carry an explicit source reference; ordinary and server-mirrored context wiring do not.
 *
 * @private
 * @sw-package discovery
 */
export function isMappingConsumer(
    consumer: ContentElementContextConsumer | undefined,
): consumer is ContentElementContextConsumer & { source: NonNullable<ContentElementContextConsumer['source']> } {
    return typeof consumer?.source === 'object' && consumer.source !== null;
}
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
 * The server supplies `inlineMappable` from the same rule used by `InlineMappingInterpolator`, so the Administration
 * does not need a copy of the stringifiable type set.
 *
 * Unlike `getCandidatesForProperty` there is no `contextTypes` filter, because the property being filled is the text
 * itself rather than a typed slot. Non-root sources are allowed too: the server resolves them through their source
 * provider, while `valueType` ensures the delivered value has a text form.
 *
 * @private
 * @sw-package discovery
 */
export function getInlineMappingCandidates(candidates: ContentSystemMappingCandidate[]): ContentSystemMappingCandidate[] {
    return candidates.filter((candidate) => candidate.inlineMappable);
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
 * Formats a valid serialized source like
 * Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference::displayName().
 *
 * Root sources use `id[.path]`; other sources use `type:id[.path]`. An omitted path identifies the whole source,
 * and configuration is not part of the name. Keep the examples in element-mapping.util.spec/source-display-name.spec.ts
 * aligned with MappingSourceReferenceTest::displayNameExamples(). Prefer catalogue labels for presentation;
 * this name also lets a stored mapping find its catalogue entry or remain readable after that entry is withdrawn.
 *
 * @private
 * @sw-package discovery
 */
export function getMappingSourceDisplayName(source: ContentSystemMappingCandidate['source']): string {
    const sourceName = source.type === 'root' ? source.id : `${source.type}:${source.id}`;

    return source.path ? `${sourceName}.${source.path}` : sourceName;
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
                path: getMappingSourceDisplayName(consumer.source),
                consumer,
            };
        }
    }

    return null;
}

/**
 * Narrows the catalogue to the candidates whose value can fill `property`.
 *
 * `contextTypes` and `compatibleTypes` are supplied by the server. The latter is computed by the same compatibility
 * rule used to admit a stored mapping, so the UI only intersects server-owned metadata with the property declaration.
 *
 * @private
 * @sw-package discovery
 */
export function getCandidatesForProperty(
    candidates: ContentSystemMappingCandidate[],
    property: ContentSystemElementTypeProperty,
): ContentSystemMappingCandidate[] {
    const contextTypes = property.contextTypes ?? [];

    return candidates.filter(
        (candidate) =>
            contextTypes.includes(candidate.contextType) &&
            (Array.isArray(property.type) ? property.type : [property.type]).some((declaredType) =>
                candidate.compatibleTypes.includes(declaredType),
            ),
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
