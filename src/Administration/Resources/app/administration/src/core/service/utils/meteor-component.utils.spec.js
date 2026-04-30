import { isMeteorComponent } from './meteor-component.utils';

describe('meteor-component.utils', () => {
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
        expect(isMeteorComponent({ type: 'bool', config: { componentName: 'sw-field', type: 'checkbox' } })).toBe(
            true,
        );
    });
});
