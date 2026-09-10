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
    'media-field': 'media',
    'sw-media-field': 'media',
    'media-collection': 'media-collection',
    'sw-media-list-selection-v2': 'media-collection',
    'entity-multi': 'entity-multi',
    'sw-entity-multi-id-select': 'entity-multi',
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
 * How a translatable property value resolves against a language chain: the
 * chain head carries its own entry, an entry further down the chain is
 * inherited, or no chain language carries an entry at all.
 *
 * @private
 * @sw-package discovery
 */
export type TranslatableEntry =
    | { state: 'own'; value: string }
    | { state: 'inherited'; value: string; fromLanguageId: string }
    | { state: 'missing' };

/**
 * Resolves a translatable property value along a language chain in serving order.
 *
 * A value that is neither `undefined` nor a non-empty map of string entries
 * throws: neither the server nor the write gate produces such a value on a
 * translatable property, so meeting one is a fault rather than a missing entry.
 *
 * @private
 * @sw-package discovery
 */
export function resolveTranslatableEntry(value: unknown, chain: readonly string[]): TranslatableEntry {
    if (value === undefined) {
        return { state: 'missing' };
    }

    if (!isStringLanguageMap(value)) {
        throw new Error(
            `A translatable property value must be undefined or a non-empty language map of strings, received ${describeTranslatableValue(value)}.`,
        );
    }

    for (const [
        index,
        languageId,
    ] of chain.entries()) {
        const entry = value[languageId];

        if (entry === undefined) {
            continue;
        }

        if (index === 0) {
            return { state: 'own', value: entry };
        }

        return { state: 'inherited', value: entry, fromLanguageId: languageId };
    }

    return { state: 'missing' };
}

/**
 * Sets the entry of one language, or removes it when `entry` is `null`.
 *
 * Every other entry travels verbatim, a non-string entry included: the write
 * route judges the carried values. Removing the anchor entry throws, since no
 * studio action offers it.
 *
 * @private
 * @sw-package discovery
 */
export function withLanguageEntry(current: unknown, languageId: string, entry: string | null): Record<string, string> {
    if (entry === null && languageId === anchorLanguageId()) {
        throw new Error('The anchor language entry of a translatable property cannot be removed.');
    }

    // The carried entries are typed as strings because the map is one; a non-string entry the server has to judge travels here too.
    const languageMap: Record<string, string> = isLanguageMap(current) ? { ...(current as Record<string, string>) } : {};

    if (entry === null) {
        delete languageMap[languageId];

        return languageMap;
    }

    languageMap[languageId] = entry;

    return languageMap;
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

function anchorLanguageId(): string {
    return Shopware.Defaults.systemLanguageId;
}

/**
 * @private
 */
export function isLanguageMap(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isStringLanguageMap(value: unknown): value is Record<string, string> {
    if (!isLanguageMap(value)) {
        return false;
    }

    const entries = Object.values(value);

    return entries.length > 0 && entries.every((entry) => typeof entry === 'string');
}

function describeTranslatableValue(value: unknown): string {
    if (value === null) {
        return 'null';
    }

    if (Array.isArray(value)) {
        return 'an array';
    }

    return typeof value === 'object' ? 'an object that is not a map of string entries' : `a ${typeof value}`;
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
