import {
    getExplicitComponentName,
    getFormFieldComponentFromType,
    getFormFieldComponentName,
    formFieldTypeComponentMap,
} from './form-field-type-mapping.utils';

describe('form-field-type-mapping.utils', () => {
    it('resolves component names from form field types', () => {
        expect(getFormFieldComponentFromType('bool')).toBe('mt-switch');
        expect(getFormFieldComponentFromType('checkbox')).toBe('mt-checkbox');
        expect(getFormFieldComponentFromType('text')).toBe('mt-text-field');
        expect(getFormFieldComponentFromType('unknown')).toBe('mt-text-field');
    });

    it('keeps the fallback map aligned with sw-form-field-renderer', () => {
        expect(formFieldTypeComponentMap).toEqual({
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
        });
    });

    it('prefers explicit component names', () => {
        expect(getFormFieldComponentName({ type: 'text', config: { componentName: 'sw-media-field' } })).toBe(
            'sw-media-field',
        );
    });

    it('resolves explicit component names from all supported field config positions', () => {
        expect(getExplicitComponentName({ componentName: 'sw-text-field' })).toBe('sw-text-field');
        expect(getExplicitComponentName({ config: { componentName: 'sw-media-field' } })).toBe('sw-media-field');
        expect(getExplicitComponentName({ custom: { componentName: 'mt-checkbox' } })).toBe('mt-checkbox');
        expect(getExplicitComponentName({ type: 'text' })).toBeNull();
    });

    it('resolves legacy sw-field through the configured type', () => {
        expect(getFormFieldComponentName({ type: 'bool', config: { componentName: 'sw-field', type: 'checkbox' } })).toBe(
            'mt-checkbox',
        );
    });

    it('uses the outer type when no component name is configured', () => {
        expect(getFormFieldComponentName({ type: 'bool' })).toBe('mt-switch');
    });

    it('can skip resolving the outer type fallback', () => {
        expect(getFormFieldComponentName({ type: 'bool' }, { resolveType: false })).toBeNull();
    });

    it('returns null without a field definition', () => {
        expect(getFormFieldComponentName()).toBeNull();
    });

    it('supports theme manager custom component config', () => {
        expect(getFormFieldComponentName({ type: 'text', custom: { componentName: 'mt-checkbox' } })).toBe('mt-checkbox');
    });

    it('resolves legacy sw-field through the custom type', () => {
        expect(getFormFieldComponentName({ type: 'bool', custom: { componentName: 'sw-field', type: 'text' } })).toBe(
            'mt-text-field',
        );
    });
});
