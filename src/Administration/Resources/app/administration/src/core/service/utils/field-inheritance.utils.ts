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
export const componentNamesSupportingMapInheritance = [
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
export const componentNamesHandlingInheritanceThemselves = [
    'mt-switch',
    'mt-checkbox',
    'sw-switch-field',
    'sw-checkbox-field',
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
    return isSupported(field, componentNamesSupportingMapInheritance);
}

/**
 * @sw-package framework
 * @private
 */
export function isFieldHandlingInheritanceItself(field: FieldInheritanceDefinition | null) {
    return isSupported(field, componentNamesHandlingInheritanceThemselves);
}
