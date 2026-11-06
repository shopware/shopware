/**
 * @sw-package framework
 */
import ShopwareSetupHmrPlugin from './index';

describe('build/vite-plugins/shopware-setup-hmr', () => {
    const transform = ShopwareSetupHmrPlugin().transform as unknown as (code: string, id: string) => string | null;

    it('adds a render update handler to Shopware setup SFCs', () => {
        const result = transform(
            'import.meta.hot.accept((mod) => { __VUE_HMR_RUNTIME__.reload(mod.default.__hmrId, mod.default); });',
            '/extension/sw-product-detail.override.vue.shopware-setup.vue',
        );

        expect(result).toContain('runtime.rerender(component.__hmrId, component.render)');
    });

    it.each([
        ['/extension/sw-product-detail.vue', 'import.meta.hot.accept(() => {})'],
        ['/extension/sw-product-detail.override.vue.shopware-setup.vue', 'export default {}'],
    ])('leaves unsupported module %s unchanged', (id, code) => {
        expect(transform(code, id)).toBeNull();
    });
});
