import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { resolveTranslatableEntry } from './element-settings.util';

/**
 * @private
 * @sw-package discovery
 */
export function getContentElementLabel(
    element: ContentElementNode,
    declaredProperties: Record<string, ContentSystemElementTypeProperty> | undefined,
    chain: readonly string[],
    fallback?: string,
): string {
    const properties = element.properties ?? {};
    const nameKeys = [
        'name',
        'label',
        'title',
    ];

    for (const key of nameKeys) {
        const value = properties[key];
        const candidate = declaredProperties?.[key]?.translatable === true ? resolveTranslatableEntry(value, chain) : value;

        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate;
        }
    }

    return fallback ?? formatComponentName(element.component);
}

/**
 * @private
 * @sw-package discovery
 */
function formatComponentName(component: string): string {
    const parts = component.split(':');

    return parts[parts.length - 1] || component;
}
