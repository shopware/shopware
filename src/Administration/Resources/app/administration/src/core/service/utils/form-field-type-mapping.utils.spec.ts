/**
 * @sw-package framework
 */

import {
    getExplicitComponentName,
    getFormFieldComponentFromType,
    getFormFieldComponentName,
    isSupported,
} from './form-field-type-mapping.utils';

describe('src/core/service/utils/form-field-type-mapping.utils', () => {
    it('resolves component names from form field types', () => {
        expect(getFormFieldComponentFromType('bool')).toBe('mt-switch');
        expect(getFormFieldComponentFromType('checkbox')).toBe('mt-checkbox');
        expect(getFormFieldComponentFromType('text')).toBe('mt-text-field');
        expect(getFormFieldComponentFromType('text-editor')).toBe('sw-text-editor');
    });

    it('returns null for unknown form field types', () => {
        expect(getFormFieldComponentFromType('unknown')).toBeNull();
    });

    it('resolves top-level explicit component name', () => {
        expect(getExplicitComponentName({ componentName: 'sw-text-field' })).toBe('sw-text-field');
    });

    it('resolves configured explicit component name', () => {
        expect(getExplicitComponentName({ config: { componentName: 'sw-media-field' } })).toBe('sw-media-field');
    });

    it('resolves custom explicit component name', () => {
        expect(getExplicitComponentName({ custom: { componentName: 'mt-checkbox' } })).toBe('mt-checkbox');
    });

    it('returns null without an explicit component name', () => {
        expect(getExplicitComponentName({ type: 'text' })).toBeNull();
    });

    describe('precedence', () => {
        it('uses top-level component name before config and custom component names', () => {
            expect(
                getFormFieldComponentName({
                    type: 'text',
                    componentName: 'sw-text-field',
                    config: { componentName: 'sw-media-field' },
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe('sw-text-field');
        });

        it('uses config component name after top-level component name is removed', () => {
            expect(
                getFormFieldComponentName({
                    type: 'text',
                    config: { componentName: 'sw-media-field' },
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe('sw-media-field');
        });

        it('uses custom component name after top-level and config component names are removed', () => {
            expect(
                getFormFieldComponentName({
                    type: 'text',
                    custom: { componentName: 'mt-checkbox' },
                }),
            ).toBe('mt-checkbox');
        });

        it('uses config type before custom and field type for legacy sw-field', () => {
            expect(
                getFormFieldComponentName({
                    type: 'checkbox',
                    componentName: 'sw-field',
                    config: { type: 'checkbox' },
                    custom: { type: 'single-entity-id-select' },
                }),
            ).toBe('mt-checkbox');
        });

        it('uses custom type after config type is removed for legacy sw-field', () => {
            expect(
                getFormFieldComponentName({
                    type: 'checkbox',
                    componentName: 'sw-field',
                    custom: { type: 'single-entity-id-select' },
                }),
            ).toBe('sw-entity-single-select');
        });

        it('uses field type after config and custom types are removed for legacy sw-field', () => {
            expect(
                getFormFieldComponentName({
                    type: 'checkbox',
                    componentName: 'sw-field',
                }),
            ).toBe('mt-checkbox');
        });
    });

    it('uses the outer type when no component name is configured', () => {
        expect(getFormFieldComponentName({ type: 'bool' })).toBe('mt-switch');
    });

    it('returns null for unknown field types', () => {
        expect(getFormFieldComponentName({ type: 'unknown' })).toBeNull();
    });

    it('can skip resolving the outer type fallback', () => {
        expect(getFormFieldComponentName({ type: 'bool' }, { resolveType: false })).toBeNull();
    });

    it('returns null without a field definition', () => {
        expect(getFormFieldComponentName()).toBeNull();
    });

    it('detects supported component names', () => {
        expect(isSupported({ componentName: 'sw-text-field' }, ['sw-text-field'])).toBe(true);
        expect(isSupported({ componentName: 'sw-media-field' }, ['sw-text-field'])).toBe(false);
    });

    it('detects supported resolved component names from field type', () => {
        expect(isSupported({ type: 'text' }, ['mt-text-field'])).toBe(true);
        expect(isSupported({ type: 'text' }, ['mt-switch'])).toBe(false);
    });

    it('detects supported resolved component names', () => {
        expect(isSupported({ type: 'text-editor' }, ['sw-text-editor'])).toBe(true);
        expect(isSupported({ type: 'unknown' }, ['mt-text-field'])).toBe(false);
    });

    it('can skip resolving the outer type fallback for support checks', () => {
        expect(isSupported({ type: 'text' }, ['mt-text-field'], { resolveType: false })).toBe(false);
    });
});
