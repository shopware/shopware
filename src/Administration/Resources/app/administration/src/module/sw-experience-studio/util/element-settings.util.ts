import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';

export type ElementPropertyControlType = 'switch' | 'number' | 'select' | 'entity' | 'media' | 'richtext' | 'text';

const ADMIN_UI_COMPONENT_CONTROL_MAP: Record<string, ElementPropertyControlType> = {
    // Meteor/basic
    'mt-switch': 'switch',
    'mt-number-field': 'number',
    'mt-select': 'select',
    'mt-text-editor': 'richtext',
    'mt-text-field': 'text',
    // Shopware/base wrappers
    'select': 'select',
    'text-editor': 'richtext',
    'entity-single-select': 'entity',
    'sw-entity-single-select': 'entity',
    'media-field': 'media',
    'sw-media-field': 'media',
};

/**
 * @private
 * @sw-package discovery
 */
export function getPropertyControlType(property: ContentSystemElementTypeProperty): ElementPropertyControlType | null {
    const adminUiComponent = property.adminUI?.component;
    if (typeof adminUiComponent === 'string' && ADMIN_UI_COMPONENT_CONTROL_MAP[adminUiComponent]) {
        return ADMIN_UI_COMPONENT_CONTROL_MAP[adminUiComponent];
    }

    if (property.type === 'boolean') {
        return 'switch';
    }

    if (property.type === 'integer' || property.type === 'number') {
        return 'number';
    }

    if (property.type === 'string') {
        if (adminUiComponent === 'text-editor' || adminUiComponent === 'mt-text-editor') {
            return 'richtext';
        }

        if (Array.isArray(property.enum) && property.enum.length > 0) {
            return 'select';
        }

        if (adminUiComponent === 'select') {
            return 'select';
        }

        return 'text';
    }

    return null;
}

/**
 * @private
 * @sw-package discovery
 */
export function getAdminUiProps(property: ContentSystemElementTypeProperty): Record<string, unknown> {
    const props = property.adminUI?.props;

    return typeof props === 'object' && props !== null ? (props as Record<string, unknown>) : {};
}

/**
 * @private
 * @sw-package discovery
 */
export function getInitialPropertyValue(
    property: ContentSystemElementTypeProperty,
    currentValue: unknown,
): string | number | boolean | null {
    if (currentValue !== undefined) {
        return currentValue as string | number | boolean | null;
    }

    if (property.default !== null && property.default !== undefined) {
        return property.default;
    }

    if (property.type === 'boolean') {
        return false;
    }

    if (property.type === 'integer' || property.type === 'number') {
        return null;
    }

    if (property.type === 'string') {
        return '';
    }

    return null;
}
