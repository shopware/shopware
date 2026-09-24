/**
 * @sw-package framework
 */

import { COMPONENT_NAME_PATTERN, inferShopwareSetupFromFilename, isDependencyFile, isReservedBindingName } from './naming';

describe('build/vue-setup-transform/naming', () => {
    it.each([
        ['src/module/sw-example.vue', { mode: 'base', componentName: 'sw-example' }],
        ['src/module/sw-example.override.vue', { mode: 'override', componentName: 'sw-example' }],
        ['src/module/sw-example/index.vue', { mode: 'base', componentName: 'sw-example' }],
        ['src\\module\\sw-example\\index.override.vue', { mode: 'override', componentName: 'sw-example' }],
        ['src/module/sw-example.vue?vue&type=script&setup=true', { mode: 'base', componentName: 'sw-example' }],
    ])('infers mode and component name from %s', (filename, expected) => {
        expect(inferShopwareSetupFromFilename(filename)).toEqual(expected);
    });

    it.each([
        ['sw-example', true],
        ['sw-example-card2', true],
        ['example', false],
        ['sw_example', false],
        ['Sw-example', false],
    ])('matches %s against the component name pattern: %s', (name, expected) => {
        expect(COMPONENT_NAME_PATTERN.test(name)).toBe(expected);
    });

    it('detects dependency files on both path separators', () => {
        expect(isDependencyFile('/app/node_modules/pkg/sw-x.vue')).toBe(true);
        expect(isDependencyFile('C:\\app\\node_modules\\pkg\\sw-x.vue')).toBe(true);
        expect(isDependencyFile('/app/src/sw-x.vue')).toBe(false);
    });

    it.each([
        ['__swSetupAnything', true],
        ['__swOverride', true],
        ['swSetup', false],
        ['Shopware', false],
    ])('reserves %s: %s', (name, expected) => {
        expect(isReservedBindingName(name)).toBe(expected);
    });
});
