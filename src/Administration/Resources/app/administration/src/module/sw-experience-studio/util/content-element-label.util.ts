import type { ContentElementNode } from 'src/core/service/content-element.types';
import { readTranslatableValue } from './element-settings.util';

/**
 * @private
 * @sw-package discovery
 */
export function getContentElementLabel(element: ContentElementNode): string {
    const properties = element.properties ?? {};
    const nameKeys = [
        'name',
        'label',
        'title',
    ];

    for (const key of nameKeys) {
        const value = properties[key];

        // a plain-object value here is only ever a language map: these keys never store any other object shape
        const candidate =
            typeof value === 'object' && value !== null && !Array.isArray(value) ? readTranslatableValue(value) : value;

        if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate;
        }
    }

    return formatComponentName(element.component);
}

/**
 * @private
 * @sw-package discovery
 */
export function formatComponentName(component: string): string {
    const parts = component.split(':');

    return parts[parts.length - 1] || component;
}
