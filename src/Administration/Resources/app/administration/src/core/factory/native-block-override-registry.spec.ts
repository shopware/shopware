/**
 * @sw-package framework
 */

import {
    hasAnyNativeBlockOverride,
    hasNativeBlockOverride,
    registerNativeBlockOverrides,
    resetNativeBlockOverrides,
} from 'src/core/factory/native-block-override-registry';

describe('core/factory/native-block-override-registry.ts', () => {
    afterEach(() => {
        resetNativeBlockOverrides();
    });

    it('starts out empty', () => {
        expect(hasAnyNativeBlockOverride()).toBe(false);
        expect(hasNativeBlockOverride('sw_demo_content')).toBe(false);
    });

    it('remembers registered block names', () => {
        registerNativeBlockOverrides([
            'sw_demo_content',
            'sw_demo_footer',
        ]);

        expect(hasAnyNativeBlockOverride()).toBe(true);
        expect(hasNativeBlockOverride('sw_demo_content')).toBe(true);
        expect(hasNativeBlockOverride('sw_demo_footer')).toBe(true);
        expect(hasNativeBlockOverride('sw_demo_header')).toBe(false);
    });

    it('accumulates the registrations of several extensions', () => {
        registerNativeBlockOverrides(['sw_demo_content']);
        registerNativeBlockOverrides([
            'sw_demo_content',
            'sw_demo_footer',
        ]);

        expect(hasNativeBlockOverride('sw_demo_content')).toBe(true);
        expect(hasNativeBlockOverride('sw_demo_footer')).toBe(true);
    });

    it('is exposed on the global Shopware object for extension entry files', () => {
        Shopware.Component.registerNativeBlockOverrides(['sw_demo_content']);

        expect(hasNativeBlockOverride('sw_demo_content')).toBe(true);
    });
});
