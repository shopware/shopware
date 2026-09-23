/**
 * @sw-package framework
 * @group disabledCompat
 */
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): reactivity and DOM stability', () => {
    setupShimSpec();

    describe('reactivity and data scope', () => {
        it('renders reactive component data accessed via {{ }} interpolation inside the Twig override', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_interpolation %}
                        <div class="data-output">{{ productName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_interpolation',
                extraData: { productName: 'My Product' },
            });

            expect(wrapper.find('.data-output').text()).toBe('My Product');
        });

        it('updates the rendered output when reactive component data used in the Twig override changes', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_update %}
                        <div class="data-output">{{ productName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_update',
                extraData: { productName: 'Initial Name' },
            });

            expect(wrapper.find('.data-output').text()).toBe('Initial Name');

            await wrapper.setData({ productName: 'Updated Name' });

            expect(wrapper.find('.data-output').text()).toBe('Updated Name');
        });

        it('evaluates a v-if directive inside the shimmed override using the host component data scope', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_vif_initial_true %}
                        <div v-if="isVisible" class="visible-content">visible</div>
                    {% endblock %}
                `,
            });

            const wrapperTrue = await createWrapper({
                blockName: 'shim_vif_initial_true',
                extraData: { isVisible: true },
            });

            expect(wrapperTrue.find('.visible-content').exists()).toBeTruthy();
        });

        it('toggles v-if content reactively when the referenced data property on the host component changes', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_vif_toggle %}
                        <div v-if="isVisible" class="visible-content">visible</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_vif_toggle',
                extraData: { isVisible: true },
            });

            expect(wrapper.find('.visible-content').exists()).toBeTruthy();

            await wrapper.setData({ isVisible: false });

            expect(wrapper.find('.visible-content').exists()).toBeFalsy();
        });

        it('passes computed properties from the host component into the Twig override template', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_computed %}
                        <div class="computed-output">{{ fullName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_computed',
                extraOptions: {
                    computed: {
                        fullName() {
                            return 'John Doe';
                        },
                    },
                },
            });

            expect(wrapper.find('.computed-output').text()).toBe('John Doe');
        });

        it('passes method references from the host component into the Twig override template', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_methods %}
                        <div class="method-output">{{ greet('World') }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_methods',
                extraOptions: {
                    methods: {
                        greet(name: string) {
                            return `Hello ${name}`;
                        },
                    },
                },
            });

            expect(wrapper.find('.method-output').text()).toBe('Hello World');
        });
    });

    describe('component instance stability', () => {
        it('preserves the DOM element identity of shimmed content across reactive data updates', async () => {
            // Vue reuses a component instance when its VNode type is the same object
            // reference between renders. If the type changes, Vue unmounts the old
            // instance and mounts a new one, which replaces the DOM node and strips
            // focus from any active input inside the shimmed content.
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_stable_dom_node %}
                        <div class="override-content">{{ label }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_stable_dom_node',
                extraData: { label: 'initial' },
            });

            const domNodeBefore = wrapper.find('.override-content').element;

            await wrapper.setData({ label: 'a' });
            await wrapper.setData({ label: 'ab' });
            await wrapper.setData({ label: 'abc' });

            // A new element reference here would mean the shim content was remounted
            // (focus lost); the same reference means it was updated in-place (focus kept).
            expect(wrapper.find('.override-content').element).toBe(domNodeBefore);
        });

        it('reflects the latest reactive data in the shimmed content after multiple updates', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_stable_data_updates %}
                        <div class="override-content">{{ label }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_stable_data_updates',
                extraData: { label: 'initial' },
            });

            await wrapper.setData({ label: 'a' });
            await wrapper.setData({ label: 'ab' });
            await wrapper.setData({ label: 'abc' });

            expect(wrapper.find('.override-content').text()).toBe('abc');
        });
    });
});
