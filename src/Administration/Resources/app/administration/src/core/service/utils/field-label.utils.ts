/**
 * @sw-package framework
 * @private
 */
import { getFormFieldComponentName } from './form-field-type-mapping.utils';

type FieldLabelDefinition = {
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

type FieldLabelOptions = {
    renderedByFormFieldRenderer?: boolean;
};

/**
 * @sw-package framework
 * @private
 */
export const fieldsHandlingLabelAndHelpText = {
    types: [
        'bool',
        'checkbox',
        'switch',
    ],
    componentNames: [
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
        // sw-text-editor still needs the wrapper label so sw-inherit-wrapper can render its inheritance switch.
        'sw-text-field',
        'sw-textarea-field',
        'sw-url-field',
    ],
};

/**
 * @sw-package framework
 * @private
 */
export function isFieldHandlingLabelAndHelpText(field: FieldLabelDefinition | null, options: FieldLabelOptions = {}) {
    const componentName = getFormFieldComponentName(field, {
        resolveType: options.renderedByFormFieldRenderer === true,
    });

    if (componentName) {
        return fieldsHandlingLabelAndHelpText.componentNames.includes(componentName);
    }

    return field?.type ? fieldsHandlingLabelAndHelpText.types.includes(field.type) : false;
}
