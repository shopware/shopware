import type { ContentElementNode } from 'src/core/service/content-element.types';

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

        if (typeof value === 'string' && value.trim() !== '') {
            return value;
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
