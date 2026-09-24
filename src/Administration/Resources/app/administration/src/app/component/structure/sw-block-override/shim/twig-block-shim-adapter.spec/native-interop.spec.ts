/**
 * @sw-package framework
 * @group disabledCompat
 */
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): interoperability with native sw-block extends', () => {
    setupShimSpec();

    describe('interoperability with native sw-block extends', () => {
        it('stacks a native <sw-block extends> on top of an already-registered Twig shim override', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_native_on_shim %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_native_on_shim',
                nativeExtensions: `
                    <sw-block extends="shim_interop_native_on_shim">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
            expect(wrapper.find('.shim-content').exists()).toBeTruthy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
        });

        it('renders content in the correct DOM order: default, then shim, then native', async () => {
            // The shim is always registered before mount (boot time), so it is
            // added to the block context first. The native extension mounts later
            // and is stacked on top of the shim.
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_shim_below_native %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_shim_below_native',
                nativeExtensions: `
                    <sw-block extends="shim_interop_shim_below_native">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            // default → shim → native (all chained via parent)
            expect(wrapper.find('.default-content + .shim-content + .native-content').exists()).toBeTruthy();
        });

        it('renders only native content when the native override has no <sw-block-parent />', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_native_no_parent %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_native_no_parent',
                nativeExtensions: `
                    <sw-block extends="shim_interop_native_no_parent">
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.shim-content').exists()).toBeFalsy();
            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
        });

        it('chains a Twig-only shim (no {% parent %}) with a native <sw-block extends> that uses <sw-block-parent />', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_twig_base_native_ext %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_twig_base_native_ext',
                nativeExtensions: `
                    <sw-block extends="shim_interop_twig_base_native_ext">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.shim-content').exists()).toBeTruthy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
            expect(wrapper.find('.shim-content + .native-content').exists()).toBeTruthy();
        });
    });
});
