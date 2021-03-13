import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-specifications';
import 'src/module/sw-product/component/sw-product-packaging-form';
import 'src/app/component/utils/sw-inherit-wrapper';

const { Component, State } = Shopware;

const packagingItemClassName = [
    '.sw-product-packaging-form__purchase-unit-field',
    '.sw-select-product__select_unit',
    '.sw-product-packaging-form__pack-unit-field',
    '.sw-product-packaging-form__pack-unit-plural-field',
    '.sw-product-packaging-form__reference-unit-field'
];

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-detail-specifications'), {
        localVue,
        mocks: {
            $t: key => key,
            $tc: key => key,
            $store: State._store
        },
        provide: {
            feature: {
                isActive: () => true
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-card': true,
            'sw-product-packaging-form': Component.build('sw-product-packaging-form'),
            'sw-product-detail-properties': true,
            'sw-product-feature-set-form': true,
            'sw-custom-field-set-renderer': true,
            'sw-container': true,
            'sw-inherit-wrapper': Component.build('sw-inherit-wrapper'),
            'sw-field': true,
            'sw-text-editor': true
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-specifications', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {},
                parentProduct: {},
                customFieldSets: [],
                advancedModeSetting: {
                    value: {
                        settings: [
                            {
                                key: 'measures_packaging',
                                label: 'sw-product.specifications.cardTitleMeasuresPackaging',
                                enabled: true,
                                name: 'specifications'
                            },
                            {
                                key: 'properties',
                                label: 'sw-product.specifications.cardTitleProperties',
                                enabled: true,
                                name: 'specifications'
                            },
                            {
                                key: 'essential_characteristics',
                                label: 'sw-product.specifications.cardTitleEssentialCharacteristics',
                                enabled: true,
                                name: 'specifications'
                            },
                            {
                                key: 'custom_products',
                                label: 'sw-product.specifications.cardTitleCustomProduct',
                                enabled: true,
                                name: 'specifications'
                            },
                            {
                                key: 'custom_fields',
                                label: 'sw-product.specifications.cardTitleCustomFields',
                                enabled: true,
                                name: 'specifications'
                            }
                        ],
                        advancedMode: {
                            enabled: true,
                            label: 'sw-product.general.textAdvancedMode'
                        }

                    }
                }
            },
            getters: {
                isLoading: () => false,
                showProductCard: () => () => {
                    return true;
                }
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be visible field items in Measures Packaging card ', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];

        await wrapper.vm.$nextTick(() => {
            // expect the some item fields in Packaging exist
            packagingItemClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(true);
            });

            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible field items in Measures Packaging card when commit setModeSettings with falsy value',
        async () => {
            const wrapper = createWrapper();
            wrapper.vm.feature = {
                isActive: () => true
            };
            await wrapper.vm.$nextTick();
            const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];

            Shopware.State.commit('swProductDetail/setModeSettings', []);

            await wrapper.vm.$nextTick(() => {
                // expect the some item fields in Packaging not exist
                packagingItemClassName.forEach(item => {
                    expect(wrapper.find(item).exists()).toBe(false);
                });

                expect(showProductCard('measures_packaging')).toBe(false);
            });
        });

    it('should be visible Properties card', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-properties').exists()).toBe(true);
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible Properties card when commit showModeSetting with falsy value', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-properties').exists()).toBe(false);
            expect(showProductCard('properties')).toBe(false);
        });
    });

    it('should be visible Essential Characteristics card', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__essential-characteristics').exists()).toBe(true);
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible Essential Characteristics card when commit showModeSetting with falsy value', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__essential-characteristics').exists()).toBe(false);
            expect(showProductCard('essential_characteristics')).toBe(false);
        });
    });

    it('should be visible Custom Product card', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__custom-product').exists()).toBe(true);
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible Custom Product card when commit showModeSetting with falsy value', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__custom-product').exists()).toBe(true);
            expect(showProductCard('custom_products')).toBe(false);
        });
    });

    it('should be visible Custom Fields card', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__custom-fields').exists()).toBe(true);
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible Custom Fields card when commit setModeSettingVisible with falsy value', async () => {
        const wrapper = createWrapper();
        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            expect(wrapper.find('.sw-product-detail-specification__custom-fields').exists()).toBe(false);
            expect(showProductCard('custom_fields')).toBe(false);
        });
    });
});
