/**
 * @sw-package framework
 */

import {
    componentNamesSupportingMapInheritance,
    isFieldHandlingInheritanceItself,
    supportsMapInheritance,
} from './field-inheritance.utils';

describe('src/core/service/utils/field-inheritance.utils', () => {
    describe('map inheritance', () => {
        it('keeps map inheritance support scoped to sw-* components', () => {
            expect(componentNamesSupportingMapInheritance.some((componentName) => componentName.startsWith('mt-'))).toBe(
                false,
            );
        });

        it('detects map inheritance support by resolved component name', () => {
            expect(supportsMapInheritance({ componentName: 'sw-text-field' })).toBe(true);
            expect(supportsMapInheritance({ componentName: 'sw-media-field' })).toBe(false);
        });

        it('detects map inheritance support by resolved field type', () => {
            expect(supportsMapInheritance({ type: 'price' })).toBe(true);
            expect(supportsMapInheritance({ type: 'bool' })).toBe(false);
        });

        it('returns false without a field definition', () => {
            expect(supportsMapInheritance(null)).toBe(false);
        });

        describe('precedence', () => {
            it('uses top-level component name before config and custom component names', () => {
                expect(
                    supportsMapInheritance({
                        componentName: 'sw-text-field',
                        config: { componentName: 'sw-media-field' },
                        custom: { componentName: 'sw-checkbox-field' },
                    }),
                ).toBe(true);
            });

            it('uses config component name after top-level component name is removed', () => {
                expect(
                    supportsMapInheritance({
                        config: { componentName: 'sw-media-field' },
                        custom: { componentName: 'sw-checkbox-field' },
                    }),
                ).toBe(false);
            });

            it('uses custom component name after top-level and config component names are removed', () => {
                expect(
                    supportsMapInheritance({
                        custom: { componentName: 'sw-checkbox-field' },
                    }),
                ).toBe(true);
            });

            it('uses config type before custom and field type for legacy sw-field', () => {
                expect(
                    supportsMapInheritance({
                        type: 'price',
                        componentName: 'sw-field',
                        config: { type: 'price' },
                        custom: { type: 'checkbox' },
                    }),
                ).toBe(true);
            });

            it('uses custom type after config type is removed for legacy sw-field', () => {
                expect(
                    supportsMapInheritance({
                        type: 'price',
                        componentName: 'sw-field',
                        custom: { type: 'checkbox' },
                    }),
                ).toBe(false);
            });

            it('uses field type after config and custom types are removed for legacy sw-field', () => {
                expect(
                    supportsMapInheritance({
                        type: 'price',
                        componentName: 'sw-field',
                    }),
                ).toBe(true);
            });
        });
    });

    describe('field handling inheritance themselves', () => {
        it('detects fields handling inheritance themselves by resolved field type', () => {
            expect(isFieldHandlingInheritanceItself({ type: 'switch' })).toBe(true);
            expect(isFieldHandlingInheritanceItself({ type: 'price' })).toBe(false);
        });

        it('detects fields handling inheritance themselves by component name', () => {
            expect(isFieldHandlingInheritanceItself({ componentName: 'mt-checkbox' })).toBe(true);
            expect(isFieldHandlingInheritanceItself({ componentName: 'mt-text-field' })).toBe(false);
        });

        describe('precedence', () => {
            it('uses top-level component name before config and custom component names', () => {
                expect(
                    isFieldHandlingInheritanceItself({
                        componentName: 'mt-checkbox',
                        config: { componentName: 'mt-text-field' },
                        custom: { componentName: 'mt-switch' },
                    }),
                ).toBe(true);
            });

            it('uses config component name after top-level component name is removed', () => {
                expect(
                    isFieldHandlingInheritanceItself({
                        config: { componentName: 'mt-text-field' },
                        custom: { componentName: 'mt-switch' },
                    }),
                ).toBe(false);
            });

            it('uses custom component name after top-level and config component names are removed', () => {
                expect(
                    isFieldHandlingInheritanceItself({
                        custom: { componentName: 'mt-switch' },
                    }),
                ).toBe(true);
            });
        });
    });
});
