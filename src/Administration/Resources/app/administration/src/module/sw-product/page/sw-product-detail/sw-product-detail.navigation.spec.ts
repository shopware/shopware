/**
 * @sw-package inventory
 */
import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';

describe('sw-product-detail navigation', () => {
    beforeAll(() => {
        Shopware.Component.override('sw-product-detail', {
            methods: { createdComponent() {} },
        });
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'returns from a variant to the parent variants tab after the parent finishes loading',
        async () => {
            const store = Shopware.Store.get('swProductDetail');
            store.$reset();
            Object.assign(store, {
                product: { id: 'variant', parentId: 'parent' },
                advancedModeSetting: { value: { advancedMode: { enabled: true } } },
            });
            const route = reactive({ name: 'sw.product.detail.base', params: { id: 'variant' } });
            const wrapper = mount(await wrapTestComponent('sw-product-detail', { sync: true }), {
                props: { productId: 'variant' },
                global: {
                    mocks: { $route: route, $router: { push: jest.fn() } },
                    provide: {
                        repositoryFactory: { create: jest.fn() },
                        mediaService: {},
                        numberRangeService: {},
                        seoUrlService: {},
                        systemConfigApiService: {},
                        customFieldDataProviderService: {},
                        entityValidationService: {},
                    },
                    stubs: {
                        'sw-page': { template: '<div><slot name="content" /></div>' },
                        'sw-card-view': { template: '<div><slot /></div>' },
                        'sw-language-info': true,
                        'sw-inheritance-warning': true,
                        'router-link': true,
                        'router-view': true,
                    },
                },
            });

            try {
                await flushPromises();
                expect(wrapper.get('[data-item-name="sw.product.detail.base"]').attributes('aria-selected')).toBe('true');

                // Vue Router updates the destination before the repository returns the parent.
                route.name = 'sw.product.detail.variants';
                route.params.id = 'parent';
                await wrapper.setProps({ productId: 'parent' });
                await flushPromises();

                expect(wrapper.find('[role="tablist"]').exists()).toBe(false);

                Object.assign(store, { product: { id: 'parent', parentId: null } });
                await flushPromises();

                expect(wrapper.get('[data-item-name="sw.product.detail.variants"]').attributes('aria-selected')).toBe(
                    'true',
                );

                // Navigating between tabs of the same product keeps working.
                route.name = 'sw.product.detail.base';
                await flushPromises();
                expect(wrapper.get('[data-item-name="sw.product.detail.base"]').attributes('aria-selected')).toBe('true');

                // Opening another variant also waits for its own data.
                route.params.id = 'another-variant';
                await wrapper.setProps({ productId: 'another-variant' });
                await flushPromises();
                expect(wrapper.find('[role="tablist"]').exists()).toBe(false);

                Object.assign(store, { product: { id: 'another-variant', parentId: 'parent' } });
                await flushPromises();
                expect(wrapper.get('[data-item-name="sw.product.detail.base"]').attributes('aria-selected')).toBe('true');
                expect(wrapper.find('[data-item-name="sw.product.detail.variants"]').exists()).toBe(false);
            } finally {
                wrapper.unmount();
                store.$reset();
            }
        },
    );
});
