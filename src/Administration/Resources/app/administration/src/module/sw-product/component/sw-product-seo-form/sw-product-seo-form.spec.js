/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

describe('module/sw-product/component/sw-product-seo-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = productEntityOverride ||
            {
                metaTitle: 'test',
            };

        const parentProduct = parentProductOverride ||
            {
                id: null,
            };

        const productVariants = [
            {
                id: 'first',
                name: 'first',
                translated: {
                    name: 'first',
                },
            },
        ];

        return mount(await wrapTestComponent('sw-product-seo-form', { sync: true }), {
            global: {
                directives: {
                    tooltip: {},
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(productVariants);
                            },
                        }),
                    },
                    validationService: {},
                },
                mocks: {
                    $store: createStore({
                        modules: {
                            swProductDetail: {
                                namespaced: true,
                                state: {
                                    product: productEntity,
                                    parentProduct,
                                },
                                getters: {
                                    isLoading: () => false,
                                },
                            },
                        },
                    }),
                },
                stubs: {
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-icon': true,
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-popover': true,
                    'sw-help-text': true,
                    'sw-field': await wrapTestComponent('sw-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-inheritance-switch': true,
                },
            },
        });
    }

    /** @tupe Wrapper */
    let wrapper;

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be visible if there are not variants', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 0,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should not be visible if there is a parent product', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 2,
            metaTitle: 'title',
        };

        const parentProduct = {
            id: 'parent-id',
        };

        wrapper = await createWrapper(productEntity, parentProduct);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should have a disabled select and a turned off switch if there is no canonical url', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 3,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchComponent = wrapper.getComponent({ name: 'sw-switch-field__wrapped' });
        const singleSelectComponent = wrapper.find('.sw-single-select');

        // check if switch is off
        expect(switchComponent.vm.value).toBe(false);

        // check if single select is disabled
        expect(singleSelectComponent.classes('is--disabled')).toBe(true);
    });

    it('should have a selected value if there is a canonical url in the Vuex store', async () => {
        const productEntity = {
            id: 'product-id',
            canonicalProductId: 'first',
            childCount: 3,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchComponent = wrapper.getComponent({ name: 'sw-switch-field__wrapped' });
        const singleSelectComponent = wrapper.get('.sw-single-select');

        // check if switch is turned on
        expect(switchComponent.vm.value).toBe(true);

        // check if single select is enabled
        expect(singleSelectComponent.attributes('disabled')).toBeUndefined();

        // check value of select field
        const textOfSelectField = singleSelectComponent.find('.sw-product-variant-info__product-name').text();
        expect(textOfSelectField).toBe('first');
    });
});
