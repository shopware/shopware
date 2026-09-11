import type { ContentElementNode } from 'src/core/service/content-element.types';
import { isLanguageMap, resolveTranslatableEntry } from './element-settings.util';

/**
 * @private
 * @sw-package discovery
 */
export function getContentElementLabel(element: ContentElementNode, chain: readonly string[]): string {
    const properties = element.properties ?? {};
    const nameKeys = [
        'name',
        'label',
        'title',
    ];

    for (const key of nameKeys) {
        const value = properties[key];

        // a plain-object value here is only ever a language map: these keys never store any other object shape
        const candidate = isLanguageMap(value) ? resolveLabelCandidate(value, chain) : value;

        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate;
        }
    }

    return formatComponentName(element.component);
}

function resolveLabelCandidate(value: unknown, chain: readonly string[]): string | null {
    return resolveTranslatableEntry(value, chain) ?? null;
}

/**
 * @private
 * @sw-package discovery
 */
export function formatComponentName(component: string): string {
    const parts = component.split(':');

    return parts[parts.length - 1] || component;
}
