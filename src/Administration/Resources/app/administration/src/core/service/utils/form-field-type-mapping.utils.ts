/**
 * @sw-package framework
 * @private
 */

type FormFieldDefinition = {
    type?: string;
    componentName?: string;
    config?: {
        componentName?: string;
        type?: string;
    } | null;
    custom?: {
        componentName?: string;
        type?: string;
    } | null;
};

type FormFieldComponentNameOptions = {
    resolveType?: boolean;
};

/**
 * @sw-package framework
 * @private
 */
export const formFieldTypeComponentMap: Record<string, string> = {
    bool: 'mt-switch',
    switch: 'mt-switch',
    textarea: 'mt-textarea',
    checkbox: 'mt-checkbox',
    colorpicker: 'mt-colorpicker',
    compactColorpicker: 'sw-compact-colorpicker',
    date: 'mt-datepicker',
    datetime: 'mt-datepicker',
    time: 'mt-datepicker',
    email: 'mt-email-field',
    float: 'mt-number-field',
    int: 'mt-number-field',
    number: 'mt-number-field',
    'multi-entity-id-select': 'sw-entity-multi-id-select',
    'multi-select': 'mt-select',
    password: 'mt-password-field',
    price: 'sw-price-field',
    radio: 'sw-radio-field',
    'single-entity-id-select': 'sw-entity-single-select',
    'single-select': 'mt-select',
    string: 'mt-text-field',
    text: 'mt-text-field',
    tagged: 'sw-tagged-field',
    url: 'mt-url-field',
};

/**
 * @sw-package framework
 * @private
 */
export function getFormFieldComponentFromType(type?: string | null) {
    return formFieldTypeComponentMap[type ?? ''] ?? 'mt-text-field';
}

/**
 * @sw-package framework
 * @private
 */
export function getExplicitComponentName(field: FormFieldDefinition | null = null) {
    return field?.componentName ?? field?.config?.componentName ?? field?.custom?.componentName ?? null;
}

function getConfiguredType(field: FormFieldDefinition | null = null) {
    return field?.config?.type ?? field?.custom?.type ?? field?.type;
}

/**
 * @sw-package framework
 * @private
 */
export function getFormFieldComponentName(
    field: FormFieldDefinition | null = null,
    options: FormFieldComponentNameOptions = { resolveType: true },
) {
    if (!field) {
        return null;
    }

    const componentName = getExplicitComponentName(field);

    // Legacy sw-field is only a placeholder; resolve it through the configured type to get the rendered component.
    if (componentName === 'sw-field') {
        return getFormFieldComponentFromType(getConfiguredType(field));
    }

    if (componentName || options.resolveType === false) {
        return componentName;
    }

    return getFormFieldComponentFromType(field?.type);
}
