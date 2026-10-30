/**
 * @sw-package discovery
 */
import { mount, flushPromises } from '@vue/test-utils';
import { defineAsyncComponent, defineComponent } from 'vue';
import type { Component } from 'vue';

async function checkDeferredChildren(): Promise<void> {
    const pending = new Map<string, (component: Component) => void>();
    const secondary = defineComponent({
        template: '<aside />',
        mounted() {
            Object.defineProperty(this.$el, 'offsetHeight', { value: 73 });
        },
    });
    const alert = defineComponent({
        template: '<aside />',
        mounted() {
            Object.defineProperty(this.$el, 'offsetHeight', { value: 17 });
        },
    });
    const child = defineComponent({
        components: { TestSecondary: secondary, TestAlert: alert },
        props: ['salesChannel', 'containerStyle'],
        emits: ['selection-change', 'product-loading'],
        template: `<section :style="containerStyle"><TestSecondary ref="cardSectionSecondary" /><TestAlert ref="alert" /><button class="select-product" @click="$emit('selection-change', [{ id: 'product' }], 'singleProducts')">Select product</button></section>`,
    });
    const names = [
        'sw-sales-channel-products-assignment-single-products',
        'sw-sales-channel-product-assignment-categories',
        'sw-sales-channel-products-assignment-dynamic-product-groups',
    ];
    const components = Object.fromEntries(
        names.map((name) => [
            name,
            defineAsyncComponent(() => new Promise<Component>((resolve) => pending.set(name, resolve))),
        ]),
    );
    const wrapper = mount(await wrapTestComponent('sw-sales-channel-products-assignment-modal', { sync: true }), {
        props: {
            salesChannel: { id: 'sales-channel', name: 'Headless' },
            isAssignProductLoading: false,
        },
        global: {
            components,
            directives: { hide: {} },
            stubs: {
                ...Object.fromEntries(names.map((name) => [name, false])),
                'sw-modal': { template: '<div><slot /><slot name="modal-footer" /></div>' },
                'sw-container': { template: '<div><slot /></div>' },
                'sw-tabs': {
                    template:
                        '<div><slot :active="\'singleProducts\'" /><slot name="content" :active="\'singleProducts\'" /></div>',
                },
                'sw-tabs-item': true,
                'mt-tabs': true,
                'sw-loader': true,
            },
        },
    });
    try {
        pending.get(names[0])!(child);
        await flushPromises();
        expect(
            (wrapper.findAllComponents(child)[0].props('containerStyle') as Record<string, string>)['grid-template-rows'],
        ).toBe('auto calc(600px - 73px)');

        pending.get(names[1])!(child);
        await flushPromises();
        expect(
            (wrapper.findAllComponents(child)[1].props('containerStyle') as Record<string, string>)['grid-template-rows'],
        ).toBe('auto calc(600px - (20px + 17px + 73px))');

        pending.get(names[2])!(child);
        await flushPromises();
        expect(
            (wrapper.findAllComponents(child)[2].props('containerStyle') as Record<string, string>)['grid-template-rows'],
        ).toBe('auto calc(600px - (20px + 17px + 73px))');

        await wrapper.get('button.select-product').trigger('click');
        await wrapper
            .findAllComponents({ name: 'mt-button' })
            .find((button) => button.props('variant') === 'primary')!
            .trigger('click');
        expect(wrapper.emitted('products-add')?.[0]?.[1]).toEqual({
            productIds: ['product'],
            categoryIds: [],
            productStreamIds: [],
        });
    } finally {
        wrapper.unmount();
    }
}

describe('product assignment with deferred tab components', () => {
    it.activeFeatureFlags(['v6.8.0.0'])('calculates layouts after each asynchronous child mounts', checkDeferredChildren);

    // @deprecated tag:v6.8.0.0 - The legacy tabs branch is removed with the major flag.
    it.deprecated('v6.8.0.0')('keeps delayed children working with legacy tabs', checkDeferredChildren);
});
