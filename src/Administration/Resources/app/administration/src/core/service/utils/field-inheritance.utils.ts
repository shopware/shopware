/**
 * @sw-package framework
 * @private
 */
import { getFormFieldComponentName } from './form-field-type-mapping.utils';

type FieldInheritanceDefinition = {
    type?: string;
    componentName?: string;
    config?: {
        componentName?: string;
        type?: string;
    };
    custom?: {
        componentName?: string;
        type?: string;
    };
};

/**
 * @sw-package framework
 * @private
 */
export const mapInheritanceComponentNames = [
    'sw-checkbox-field',
    'sw-colorpicker',
    'sw-compact-colorpicker',
    'sw-datepicker',
    'sw-email-field',
    'sw-number-field',
    'sw-password-field',
    'sw-price-field',
    'sw-radio-field',
    'sw-select-field',
    'sw-snippet-field',
    'sw-switch-field',
    'sw-tagged-field',
    'sw-text-field',
    'sw-textarea-field',
    'sw-url-field',
];

/**
 * @sw-package framework
 * @private
 */
export const fieldHandlingInheritanceItselfComponentNames = [
    'mt-switch',
    'mt-checkbox',
    'sw-switch-field',
    'sw-checkbox-field',
];

/**
 * @sw-package framework
 * @private
 */
export const meteorComponentNames = [
    'mt-checkbox',
    'mt-colorpicker',
    'mt-datepicker',
    'mt-email-field',
    'mt-number-field',
    'mt-password-field',
    'mt-select',
    'mt-switch',
    'mt-text-field',
    'mt-textarea',
    'mt-url-field',
    // internally these map to their meteor component equivalent
    'sw-checkbox-field',
    'sw-colorpicker',
    'sw-datepicker',
    'sw-number-field',
    'sw-password-field',
    'sw-switch-field',
    'sw-text-editor',
    'sw-text-field',
    'sw-textarea-field',
    'sw-url-field',
];

function isSupported(field: FieldInheritanceDefinition | null, componentNames: string[]) {
    const componentName = getFormFieldComponentName(field);

    return componentName ? componentNames.includes(componentName) : false;
}

/**
 * @sw-package framework
 * @private
 */
export function supportsMapInheritance(field: FieldInheritanceDefinition | null) {
    return isSupported(field, mapInheritanceComponentNames);
}

/**
 * @sw-package framework
 * @private
 */
export function isMeteorComponent(field: FieldInheritanceDefinition | null) {
    return isSupported(field, meteorComponentNames);
}

/**
 * @sw-package framework
 * @private
 */
export function isFieldHandlingInheritanceItself(field: FieldInheritanceDefinition | null) {
    return isSupported(field, fieldHandlingInheritanceItselfComponentNames);
}
