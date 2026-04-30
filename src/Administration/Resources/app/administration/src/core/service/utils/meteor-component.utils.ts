/**
 * @sw-package framework
 * @private
 */
import { getFormFieldComponentName } from './form-field-type-mapping.utils';

type MeteorComponentDefinition = {
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
export function isMeteorComponent(field: MeteorComponentDefinition | null) {
    const componentName = getFormFieldComponentName(field);

    return componentName ? meteorComponentNames.includes(componentName) : false;
}
