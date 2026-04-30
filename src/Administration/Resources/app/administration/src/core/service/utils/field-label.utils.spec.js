import { fieldsHandlingLabelAndHelpText, isFieldHandlingLabelAndHelpText } from './field-label.utils';

describe('field-label.utils', () => {
    it('keeps label and help text support by types and component names', () => {
        expect(fieldsHandlingLabelAndHelpText.types).toEqual([
            'bool',
            'checkbox',
            'switch',
        ]);
        expect(fieldsHandlingLabelAndHelpText.componentNames).toEqual(expect.arrayContaining([
            'mt-text-field',
            'mt-switch',
            'mt-checkbox',
            'sw-text-field',
            'sw-switch-field',
            'sw-checkbox-field',
        ]));
        expect(fieldsHandlingLabelAndHelpText.themeManager).toBeUndefined();
    });

    it('detects fields handling label and help text by supported field type', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'checkbox' })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ type: 'text' })).toBe(false);
    });

    it('detects fields handling label and help text by resolved field type for form field renderer usage', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'text' }, { renderedByFormFieldRenderer: true })).toBe(true);
    });

    it('detects fields handling label and help text by direct component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-checkbox-field' })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'mt-text-field' })).toBe(true);
    });

    it('detects fields handling label and help text by configured component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ config: { componentName: 'sw-switch-field' } })).toBe(true);
    });

    it('detects fields handling label and help text by custom component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ custom: { componentName: 'mt-checkbox' } })).toBe(true);
    });

    it('resolves legacy sw-field before checking label and help text handling', () => {
        const field = {
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'checkbox',
            },
        };

        expect(isFieldHandlingLabelAndHelpText(field)).toBe(true);
    });

    it('detects fields handling label and help text from the central support list', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'switch' })).toBe(true);
    });

    it('returns false for label and help text handling without matching config', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'text' })).toBe(false);
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-text-editor' })).toBe(false);
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-media-field' })).toBe(false);
        expect(isFieldHandlingLabelAndHelpText(null)).toBe(false);
    });
});
