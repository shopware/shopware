import type { ContentElementNode } from '../types/content-element.types';

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

function isContentElementNode(element: unknown): element is ContentElementNode {
    if (typeof element !== 'object' || element === null) {
        return false;
    }

    const record = element as Record<string, unknown>;

    return typeof record.id === 'string' && typeof record.component === 'string';
}

/**
 * @private
 * @sw-package discovery
 */
export function castContentElementNodes(layout: unknown): ContentElementNode[] {
    if (!Array.isArray(layout)) {
        return [];
    }

    return layout.filter(isContentElementNode);
}
