import {
    fieldHandlingInheritanceItselfComponentNames,
    isFieldHandlingInheritanceItself,
    isMeteorComponent,
    mapInheritanceComponentNames,
    supportsMapInheritance,
} from './field-inheritance.utils';

describe('field-inheritance.utils', () => {
    it('keeps field handling inheritance support in a flattened list', () => {
        expect(fieldHandlingInheritanceItselfComponentNames).toEqual([
            'mt-switch',
            'mt-checkbox',
            'sw-switch-field',
            'sw-checkbox-field',
        ]);
        expect(mapInheritanceComponentNames.some((componentName) => componentName.startsWith('mt-'))).toBe(false);
    });

    it('detects map inheritance support by resolved component from field type', () => {
        expect(supportsMapInheritance({ type: 'price' })).toBe(true);
        expect(supportsMapInheritance({ type: 'text' })).toBe(false);
    });

    it('detects map inheritance support by direct component name', () => {
        expect(supportsMapInheritance({ componentName: 'sw-text-field' })).toBe(true);
    });

    it('detects map inheritance support by configured component name', () => {
        const field = {
            config: {
                componentName: 'sw-snippet-field',
            },
        };

        expect(supportsMapInheritance(field)).toBe(true);
    });

    it('detects map inheritance support by custom component name', () => {
        const field = {
            custom: {
                componentName: 'sw-checkbox-field',
            },
        };

        expect(supportsMapInheritance(field)).toBe(true);
    });

    it('does not treat legacy sw-field meteor fallbacks as map inheritance support', () => {
        const field = {
            type: 'bool',
            config: {
                componentName: 'sw-field',
                type: 'checkbox',
            },
        };

        expect(supportsMapInheritance(field)).toBe(false);
    });

    it('returns false for map inheritance support without matching config', () => {
        expect(supportsMapInheritance({ componentName: 'sw-media-field' })).toBe(false);
        expect(supportsMapInheritance(null)).toBe(false);
    });

    it('detects meteor components by resolved component from field type', () => {
        expect(isMeteorComponent({ type: 'checkbox' })).toBe(true);
        expect(isMeteorComponent({ componentName: 'sw-media-field' })).toBe(false);
    });

    it('detects meteor components by component name', () => {
        expect(isMeteorComponent({ config: { componentName: 'sw-text-field' } })).toBe(true);
        expect(isMeteorComponent({ config: { componentName: 'sw-text-editor' } })).toBe(true);
    });

    it('detects supported meteor components from the central support list', () => {
        expect(isMeteorComponent({ type: 'bool' })).toBe(true);
        expect(isMeteorComponent({ type: 'bool', config: { componentName: 'sw-field', type: 'checkbox' } })).toBe(true);
    });

    it('detects fields handling inheritance themselves by resolved component from field type', () => {
        expect(isFieldHandlingInheritanceItself({ type: 'switch' })).toBe(true);
        expect(isFieldHandlingInheritanceItself({ type: 'text' })).toBe(false);
    });

    it('detects fields handling inheritance themselves by component name', () => {
        expect(isFieldHandlingInheritanceItself({ custom: { componentName: 'mt-checkbox' } })).toBe(true);
    });
});
