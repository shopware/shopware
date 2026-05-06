/**
 * @sw-package framework
 * @private
 */
import { isSupported, type FormFieldDefinition } from './form-field-type-mapping.utils';

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

/**
 * @sw-package framework
 * @private
 */
export function supportsMapInheritance(field: FormFieldDefinition | null) {
    return isSupported(field, componentNamesSupportingMapInheritance);
}

/**
 * @sw-package framework
 * @private
 */
export function isFieldHandlingInheritanceItself(field: FormFieldDefinition | null) {
    return isSupported(field, componentNamesHandlingInheritanceThemselves);
}
