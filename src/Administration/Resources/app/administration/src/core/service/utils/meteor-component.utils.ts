/**
 * @sw-package framework
 * @private
 */
import { isSupported, type FormFieldDefinition } from './form-field-type-mapping.utils';

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

/**
 * @sw-package framework
 * @private
 */
export function isMeteorComponent(field: FormFieldDefinition | null) {
    return isSupported(field, meteorComponentNames, { noTypeFallback: true });
}
