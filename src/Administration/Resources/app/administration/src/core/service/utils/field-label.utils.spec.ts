/**
 * @sw-package framework
 */

import { fieldsHandlingLabelAndHelpText, isFieldHandlingLabelAndHelpText } from './field-label.utils';

describe('src/core/service/utils/field-label.utils', () => {
    it('keeps label and help text support by types and component names', () => {
        expect(fieldsHandlingLabelAndHelpText.types).toEqual([
            'bool',
            'checkbox',
            'switch',
        ]);
        expect(fieldsHandlingLabelAndHelpText.componentNames).toHaveLength(27);
    });

    it('detects fields handling label and help text by supported field type', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'checkbox' })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ type: 'text' })).toBe(false);
    });

    it('detects fields handling label and help text by resolved component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-checkbox-field' })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-media-field' })).toBe(false);
    });

    it('detects fields handling label and help text by configured component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ config: { componentName: 'sw-switch-field' } })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ config: { componentName: 'sw-media-field' } })).toBe(false);
    });

    it('detects fields handling label and help text by custom component name', () => {
        expect(isFieldHandlingLabelAndHelpText({ custom: { componentName: 'mt-checkbox' } })).toBe(true);
        expect(isFieldHandlingLabelAndHelpText({ custom: { componentName: 'sw-media-field' } })).toBe(false);
    });

    it('uses the form field renderer type fallback when requested', () => {
        expect(isFieldHandlingLabelAndHelpText({ type: 'text' }, { renderedByFormFieldRenderer: true })).toBe(true);
        expect(
            isFieldHandlingLabelAndHelpText({ type: 'single-entity-id-select' }, { renderedByFormFieldRenderer: true }),
        ).toBe(false);
    });

    it('keeps sw-text-editor label and help text in the wrapper', () => {
        expect(isFieldHandlingLabelAndHelpText({ componentName: 'sw-text-editor' })).toBe(false);
    });

    it('returns false without a field definition', () => {
        expect(isFieldHandlingLabelAndHelpText(null)).toBe(false);
    });

    describe('precedence', () => {
        it('uses top-level component name before config and custom component names', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    componentName: 'sw-checkbox-field',
                    config: { componentName: 'sw-media-field' },
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe(true);
        });

        it('uses config component name after top-level component name is removed', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    config: { componentName: 'sw-media-field' },
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe(false);
        });

        it('uses custom component name after top-level and config component names are removed', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe(true);
        });

        it('uses config type before custom and field type for legacy sw-field', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    type: 'checkbox',
                    config: { componentName: 'sw-field', type: 'checkbox' },
                    custom: { componentName: 'sw-field', type: 'single-entity-id-select' },
                }),
            ).toBe(true);
        });

        it('uses custom type after config type is removed for legacy sw-field', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    type: 'checkbox',
                    custom: { componentName: 'sw-field', type: 'single-entity-id-select' },
                }),
            ).toBe(false);
        });

        it('uses field type after config and custom types are removed for legacy sw-field', () => {
            expect(
                isFieldHandlingLabelAndHelpText({
                    type: 'checkbox',
                }),
            ).toBe(true);
        });
    });
});
