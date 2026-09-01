import type {
    ContentSystemElementAdminUiVisibleWhenCondition,
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';

/**
 * @private
 * @sw-package discovery
 */
export type ElementPropertyControlType =
    | 'switch'
    | 'number'
    | 'slider'
    | 'color'
    | 'select'
    | 'radio-panel'
    | 'entity'
    | 'entity-multi'
    | 'media'
    | 'media-collection'
    | 'richtext'
    | 'text'
    | 'responsive-number'
    | 'box-spacing';

const ADMIN_UI_COMPONENT_CONTROL_MAP: Record<string, ElementPropertyControlType> = {
    // Meteor/basic
    'mt-switch': 'switch',
    'mt-number-field': 'number',
    'mt-slider': 'slider',
    'mt-colorpicker': 'color',
    'mt-select': 'select',
    'radio-panel': 'radio-panel',
    'mt-text-editor': 'richtext',
    'mt-text-field': 'text',
    // Shopware/base wrappers
    color: 'color',
    select: 'select',
    switch: 'switch',
    number: 'number',
    slider: 'slider',
    text: 'text',
    'text-editor': 'richtext',
    'entity-single-select': 'entity',
    'sw-entity-single-select': 'entity',
    'entity-multi-id-select': 'entity-multi',
    'sw-entity-multi-id-select': 'entity-multi',
    'media-field': 'media',
    'sw-media-field': 'media',
    'media-collection': 'media-collection',
    'sw-media-list-selection-v2': 'media-collection',
    'responsive-number': 'responsive-number',
    'box-spacing': 'box-spacing',
};

/**
 * Returns the property key used by the persisted layout.
 *
 * Reference properties using `resolvedBy` are exposed through the default
 * binding specification. Its entity loader reads the reference ID from a
 * separate storage property.
 *
 * @private
 * @sw-package discovery
 */
export function getElementPropertyStorageKey(
    typeSpecification: Pick<ContentSystemElementTypeSpecification, 'bindingSpecifications'>,
    propertyKey: string,
): string {
    for (const bindingSpecification of Object.values(typeSpecification.bindingSpecifications ?? {})) {
        const resolve = bindingSpecification.default ? bindingSpecification.resolves[propertyKey] : undefined;

        if (!resolve || (resolve.loader !== 'entity' && resolve.loader !== 'entity_collection')) {
            continue;
        }

        const resolvedBy = resolve.config.property;

        if (typeof resolvedBy === 'string' && resolvedBy.length > 0) {
            return resolvedBy;
        }
    }

    return propertyKey;
}

/**
 * @private
 * @sw-package discovery
 */
export function getPropertyControlType(property: ContentSystemElementTypeProperty): ElementPropertyControlType | null {
    const adminUiComponent = property.adminUI?.component;
    if (typeof adminUiComponent === 'string' && ADMIN_UI_COMPONENT_CONTROL_MAP[adminUiComponent]) {
        return ADMIN_UI_COMPONENT_CONTROL_MAP[adminUiComponent];
    }

    if (propertyHasType(property, 'boolean')) {
        return 'switch';
    }

    if (propertyHasType(property, 'integer') || propertyHasType(property, 'number')) {
        return 'number';
    }

    if (propertyHasType(property, 'string')) {
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

    return typeof props === 'object' && props !== null ? props : {};
}

/**
 * @private
 * @sw-package discovery
 */
export function getAdminUiHelpText(property: ContentSystemElementTypeProperty): string | null {
    const helpText = property.adminUI?.helpText;

    return typeof helpText === 'string' && helpText.length > 0 ? helpText : null;
}

/**
 * @private
 * @sw-package discovery
 */
export function isPropertyVisible(
    property: ContentSystemElementTypeProperty,
    propertyValues: Record<string, unknown>,
): boolean {
    const visibleWhen = property.adminUI?.visibleWhen;

    if (!visibleWhen) {
        return true;
    }

    if (Array.isArray(visibleWhen)) {
        if (visibleWhen.length === 0 || !visibleWhen.every(isVisibleWhenCondition)) {
            return true;
        }

        return visibleWhen.every((condition) => matchesVisibleWhenCondition(condition, propertyValues));
    }

    if (!isVisibleWhenCondition(visibleWhen)) {
        return true;
    }

    return matchesVisibleWhenCondition(visibleWhen, propertyValues);
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

    if (propertyHasType(property, 'boolean')) {
        return false;
    }

    if (propertyHasType(property, 'integer') || propertyHasType(property, 'number')) {
        return null;
    }

    if (propertyHasType(property, 'string')) {
        return '';
    }

    return null;
}

function propertyHasType(property: ContentSystemElementTypeProperty, type: string): boolean {
    if (Array.isArray(property.type)) {
        return property.type.includes(type);
    }

    return property.type === type;
}

function matchesVisibleWhenCondition(
    condition: ContentSystemElementAdminUiVisibleWhenCondition,
    propertyValues: Record<string, unknown>,
): boolean {
    if (typeof condition.field !== 'string' || condition.field.length === 0) {
        return true;
    }

    const operator = getVisibleWhenOperator(condition);

    if (!operator) {
        return true;
    }

    const value = propertyValues[condition.field];

    switch (operator) {
        case 'equals':
            return value === condition.equals;
        case 'notEquals':
            return value !== condition.notEquals;
        case 'in':
            return Array.isArray(condition.in) && condition.in.includes(value as string | number | boolean | null);
        case 'notIn':
            return Array.isArray(condition.notIn) && !condition.notIn.includes(value as string | number | boolean | null);
        case 'isEmpty':
            return condition.isEmpty === true ? isEmptyValue(value) : true;
        case 'isNotEmpty':
            return condition.isNotEmpty === true ? !isEmptyValue(value) : true;
        default:
            return true;
    }
}

function isVisibleWhenCondition(value: unknown): value is ContentSystemElementAdminUiVisibleWhenCondition {
    return typeof value === 'object' && value !== null;
}

function getVisibleWhenOperator(
    condition: ContentSystemElementAdminUiVisibleWhenCondition,
): 'equals' | 'notEquals' | 'in' | 'notIn' | 'isEmpty' | 'isNotEmpty' | null {
    const operators: Array<'equals' | 'notEquals' | 'in' | 'notIn' | 'isEmpty' | 'isNotEmpty'> = [
        'equals',
        'notEquals',
        'in',
        'notIn',
        'isEmpty',
        'isNotEmpty',
    ];

    const usedOperators = operators.filter((operator) => condition[operator] !== undefined);

    return usedOperators.length === 1 ? usedOperators[0] : null;
}

function isEmptyValue(value: unknown): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return false;
}
