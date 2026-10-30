/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';

/**
 * Re-renders updated Shopware setup SFCs after Vue's normal HMR callback.
 *
 * Native override SFCs register their `<sw-block extends>` slot through the hidden override mount.
 * Vue's full component reload does not refresh the slot currently held by Shopware's block context,
 * so a render-only pass is needed to replace that slot and invalidate the rendered block.
 * @private
 */
export default function ShopwareSetupHmrPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-shopware-setup-hmr',
        apply: 'serve',
        enforce: 'post',

        transform(code, id) {
            if (!id.includes('.shopware-setup.vue') || !code.includes('import.meta.hot.accept')) {
                return null;
            }

            return `${code}
if (import.meta.hot) {
    import.meta.hot.accept((mod) => {
        const component = mod?.default;
        const runtime = globalThis.__VUE_HMR_RUNTIME__;

        if (component?.__hmrId && component.render && runtime) {
            runtime.rerender(component.__hmrId, component.render);
        }
    });
}
`;
        },
    };
}
