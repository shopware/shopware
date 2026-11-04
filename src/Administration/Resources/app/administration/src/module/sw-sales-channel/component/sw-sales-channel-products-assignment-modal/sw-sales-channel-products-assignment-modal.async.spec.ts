/**
 * @sw-package discovery
 */
import { mount, flushPromises } from '@vue/test-utils';
import { defineAsyncComponent, defineComponent } from 'vue';
import type { Component } from 'vue';

function withHeight(offsetHeight: number): Component {
    return defineComponent({
        template: '<div><slot /></div>',
        mounted(this: { $el: HTMLElement }) {
            Object.defineProperty(this.$el, 'offsetHeight', { value: offsetHeight });
        },
    });
}

function deferred(component: Component) {
    let resolve: (value: Component) => void = () => {};
    const asyncComponent = defineAsyncComponent(
        () =>
            new Promise<Component>((resolveLoader) => {
                resolve = resolveLoader;
            }),
    );

    return { component, asyncComponent, resolve: (): void => resolve(component) };
}

const tabWithAlert = defineComponent({
    components: { TabSection: withHeight(73), TabAlert: withHeight(17) },
    props: ['salesChannel', 'containerStyle'],
    template: '<section><TabAlert ref="alert" /><TabSection ref="cardSectionSecondary" /></section>',
});

async function createWrapper() {
    const tabs = {
        singleProducts: deferred(
            await wrapTestComponent('sw-sales-channel-products-assignment-single-products', { sync: true }),
        ),
        categories: deferred(defineComponent({ extends: tabWithAlert })),
        productGroups: deferred(defineComponent({ extends: tabWithAlert })),
    };

    const wrapper = mount(await wrapTestComponent('sw-sales-channel-products-assignment-modal', { sync: true }), {
        props: {
            salesChannel: { id: 'sales-channel', name: 'Headless' },
            isAssignProductLoading: false,
        },
        global: {
            components: {
                'sw-sales-channel-products-assignment-single-products': tabs.singleProducts.asyncComponent,
                'sw-sales-channel-product-assignment-categories': tabs.categories.asyncComponent,
                'sw-sales-channel-products-assignment-dynamic-product-groups': tabs.productGroups.asyncComponent,
            },
            directives: { hide: {} },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve(Object.assign([], { total: 0 })) }),
                },
            },
            stubs: {
                'sw-sales-channel-products-assignment-single-products': false,
                'sw-sales-channel-product-assignment-categories': false,
                'sw-sales-channel-products-assignment-dynamic-product-groups': false,
                'sw-modal': { template: '<div><slot /><slot name="modal-footer" /></div>' },
                'sw-container': { template: '<div><slot /></div>' },
                'sw-tabs': {
                    template:
                        '<div><slot :active="\'singleProducts\'" /><slot name="content" :active="\'singleProducts\'" /></div>',
                },
                'sw-tabs-item': true,
                'mt-tabs': true,
                'sw-loader': true,
                'mt-card': { template: '<div><slot /></div>' },
                'sw-card-section': withHeight(73),
                'sw-simple-search-field': true,
                'sw-entity-listing': true,
                'mt-empty-state': true,
            },
        },
    });

    return { wrapper, tabs };
}

async function expectLayoutAfterDeferredTabsMount(): Promise<void> {
    const { wrapper, tabs } = await createWrapper();

    for (const tab of Object.values(tabs)) {
        tab.resolve();
    }
    await flushPromises();

    expect(wrapper.getComponent(tabs.singleProducts.component).props('containerStyle')).toMatchObject({
        'grid-template-rows': 'auto calc(600px - 73px)',
    });
    expect(wrapper.getComponent(tabs.categories.component).props('containerStyle')).toMatchObject({
        'grid-template-rows': 'auto calc(600px - (20px + 17px + 73px))',
    });
    expect(wrapper.getComponent(tabs.productGroups.component).props('containerStyle')).toMatchObject({
        'grid-template-rows': 'auto calc(600px - (20px + 17px + 73px))',
    });

    wrapper.unmount();
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal (deferred tabs)', () => {
    it.activeFeatureFlags(['v6.8.0.0'])(
        'calculates each tab layout once its deferred component mounts',
        expectLayoutAfterDeferredTabsMount,
    );

    // @deprecated tag:v6.8.0 - The legacy tabs branch is removed with the major flag.
    it.deprecated('v6.8.0.0')(
        'calculates each legacy tab layout once its deferred component mounts',
        expectLayoutAfterDeferredTabsMount,
    );
});
