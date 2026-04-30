import { isMeteorComponent } from './meteor-component.utils';

describe('src/core/service/utils/meteor-component.utils', () => {
    const meteorComponentNames = [
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
    ];

    const wrappedMeteorComponentNames = [
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

    it('detects meteor components by resolved component from field type', () => {
        expect(isMeteorComponent({ type: 'checkbox' })).toBe(true);
        expect(isMeteorComponent({ type: 'single-entity-id-select' })).toBe(false);
    });

    it.each(meteorComponentNames)('detects meteor component %s by resolved component name', (componentName) => {
        expect(isMeteorComponent({ componentName })).toBe(true);
    });

    it.each(wrappedMeteorComponentNames)('detects wrapped meteor component %s by resolved component name', (componentName) => {
        expect(isMeteorComponent({ componentName })).toBe(true);
    });

    it('returns false for unsupported component names', () => {
        expect(isMeteorComponent({ componentName: 'sw-media-field' })).toBe(false);
    });

    it('detects meteor components by configured component name', () => {
        expect(isMeteorComponent({ config: { componentName: 'mt-text-field' } })).toBe(true);
        expect(isMeteorComponent({ config: { componentName: 'sw-media-field' } })).toBe(false);
    });

    it('detects meteor components by custom component name', () => {
        expect(isMeteorComponent({ custom: { componentName: 'mt-checkbox' } })).toBe(true);
        expect(isMeteorComponent({ custom: { componentName: 'sw-media-field' } })).toBe(false);
    });

    describe('precedence', () => {
        it('uses top-level component name before config and custom component names', () => {
            expect(isMeteorComponent({
                componentName: 'mt-text-field',
                config: { componentName: 'sw-media-field', },
                custom: { componentName: 'mt-checkbox', },
            })).toBe(true);
        });

        it('uses config component name after top-level component name is removed', () => {
            expect(isMeteorComponent({
                config: { componentName: 'sw-media-field', },
                custom: { componentName: 'mt-checkbox', },
            })).toBe(false);
        });

        it('uses custom component name after top-level and config component names are removed', () => {
            expect(isMeteorComponent({
                custom: { componentName: 'mt-checkbox', },
            })).toBe(true);
        });

        it('uses config type before custom and field type for legacy sw-field', () => {
            expect(isMeteorComponent({
                type: 'checkbox',
                componentName: 'sw-field',
                config: { type: 'checkbox', },
                custom: { type: 'single-entity-id-select', },
            })).toBe(true);
        });

        it('uses custom type after config type is removed for legacy sw-field', () => {
            expect(isMeteorComponent({
                type: 'checkbox',
                componentName: 'sw-field',
                custom: { type: 'single-entity-id-select', },
            })).toBe(false);
        });

        it('uses field type after config and custom types are removed for legacy sw-field', () => {
            expect(isMeteorComponent({
                type: 'checkbox',
                componentName: 'sw-field',
            })).toBe(true);
        });
    });
});
