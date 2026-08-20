/**
 * @sw-package framework
 */

import {
    getNativeBlockExtensionTargets,
    hasNativeSetupExtensionTarget,
    registerNativeExtensionTargets,
    resetNativeExtensionTargets,
} from 'src/core/factory/native-extension-targets';

describe('core/factory/native-extension-targets.ts', () => {
    afterEach(() => {
        resetNativeExtensionTargets();
    });

    it('registers the target component of an override without blocks', () => {
        registerNativeExtensionTargets({ component: 'sw-product-detail' });

        expect(hasNativeSetupExtensionTarget('sw-product-detail')).toBe(true);
        expect(getNativeBlockExtensionTargets().size).toBe(0);
    });

    it('registers every extended block name', () => {
        registerNativeExtensionTargets({
            component: 'sw-product-detail',
            blocks: [
                'sw_product_detail_base',
                'sw_product_detail_content',
            ],
        });

        expect(Array.from(getNativeBlockExtensionTargets()).sort()).toEqual([
            'sw_product_detail_base',
            'sw_product_detail_content',
        ]);
    });

    it('merges the targets of multiple override files', () => {
        registerNativeExtensionTargets({
            component: 'sw-product-detail',
            blocks: ['sw_product_detail_base'],
        });
        registerNativeExtensionTargets({
            component: 'sw-order-detail',
            blocks: ['sw_product_detail_base'],
        });

        expect(getNativeBlockExtensionTargets().size).toBe(1);
        expect(hasNativeSetupExtensionTarget('sw-order-detail')).toBe(true);
    });

    it('reports unknown components as untargeted', () => {
        expect(hasNativeSetupExtensionTarget('sw-unknown')).toBe(false);
    });

    it('clears all registered targets on reset', () => {
        registerNativeExtensionTargets({
            component: 'sw-product-detail',
            blocks: ['sw_product_detail_base'],
        });

        resetNativeExtensionTargets();

        expect(hasNativeSetupExtensionTarget('sw-product-detail')).toBe(false);
        expect(getNativeBlockExtensionTargets().size).toBe(0);
    });
});
