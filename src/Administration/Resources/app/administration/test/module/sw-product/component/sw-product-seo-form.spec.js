import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-seo-form';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/base/sw-product-variant-info';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';

describe('module/sw-product/component/sw-product-seo-form', () => {
    function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = productEntityOverride ||
            {
                metaTitle: 'test'
            };

        const parentProduct = parentProductOverride ||
            {
                id: null
            };

        const productVariants = [
            {
                id: 'first',
                name: 'first',
                translated: {
                    name: 'first'
                }
            }
        ];

        const localVue = createLocalVue();
        localVue.directive('tooltip', {});

        return shallowMount(Shopware.Component.build('sw-product-seo-form'), {
            localVue,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(productVariants);
                        }
                    })
                }
            },
            mocks: {
                $store: new Vuex.Store({
                    modules: {
                        swProductDetail: {
                            namespaced: true,
                            state: {
                                product: productEntity,
                                parentProduct
                            },
                            getters: {
                                isLoading: () => false
                            }
                        }
                    }
                })
            },
            stubs: {
                'sw-inherit-wrapper': true,
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-single-select': Shopware.Component.build('sw-single-select'),
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-icon': true,
                'sw-product-variant-info': Shopware.Component.build('sw-product-variant-info'),
                'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
                'sw-select-result': Shopware.Component.build('sw-select-result'),
                'sw-popover': true
            }
        });
    }

    /** @tupe Wrapper */
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be visible if there are not variants', () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 0,
            metaTitle: 'title'
        };

        wrapper = createWrapper(productEntity);

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should not be visible if there is a parent product', () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 2,
            metaTitle: 'title'
        };

        const parentProduct = {
            id: 'parent-id'
        };

        wrapper = createWrapper(productEntity, parentProduct);

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should have a disabled select and a turned off switch if there is no canonical url', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 3,
            metaTitle: 'title'
        };

        wrapper = createWrapper(productEntity);

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        // check if switch is off
        expect(switchComponent.vm.value).toBe(false);

        // check if single select is disabled
        expect(singleSelectComponent.attributes('disabled')).toBe('disabled');
    });

    it('should have a selected value if there is a canonical url in the Vuex store', async () => {
        const productEntity = {
            id: 'product-id',
            canonicalProductId: 'first',
            childCount: 3,
            metaTitle: 'title'
        };

        wrapper = createWrapper(productEntity);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-single-select');

        // check if switch is turned on
        expect(switchComponent.vm.value).toBe(true);

        // check if single select is enabled
        expect(singleSelectComponent.attributes('disabled')).toBe(undefined);

        // check value of select field
        const textOfSelectField = singleSelectComponent.find('.sw-product-variant-info__product-name').text();
        expect(textOfSelectField).toBe('first');
    });
});
