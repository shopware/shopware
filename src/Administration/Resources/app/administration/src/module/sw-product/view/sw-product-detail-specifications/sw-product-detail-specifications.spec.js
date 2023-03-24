/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDetailSpecifications from 'src/module/sw-product/view/sw-product-detail-specifications';
import swProductPackagingForm from 'src/module/sw-product/component/sw-product-packaging-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

const { State, Utils } = Shopware;

const packagingItemClassName = [
    '.sw-product-packaging-form__purchase-unit-field',
    '.sw-select-product__select_unit',
    '.sw-product-packaging-form__pack-unit-field',
    '.sw-product-packaging-form__pack-unit-plural-field',
    '.sw-product-packaging-form__reference-unit-field'
];

Shopware.Component.register('sw-product-detail-specifications', swProductDetailSpecifications);
Shopware.Component.register('sw-product-packaging-form', swProductPackagingForm);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-product-detail-specifications'), {
        localVue,
        provide: {
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
            'sw-product-packaging-form': await Shopware.Component.build('sw-product-packaging-form'),
            'sw-product-properties': true,
            'sw-product-feature-set-form': true,
            'sw-custom-field-set-renderer': true,
            'sw-container': true,
            'sw-inherit-wrapper': {
                template:
                        `<div class="sw-inherit-wrapper">
                        <slot name="content" v-bind="{ updateCurrentValue }"></slot>
                    </div>`,
                methods: {
                    updateCurrentValue: () => {}
                }
            },
            'sw-field': true,
            'sw-text-editor': true,
            'sw-entity-single-select': true,
            'sw-skeleton': true,
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-specifications', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            ...productStore,
            state: {
                ...productStore.state,
                product: {
                    isNew: () => false,
                },
                parentProduct: {},
                customFieldSets: [],
                modeSettings: [
                    'measures_packaging',
                    'properties',
                    'essential_characteristics',
                    'custom_fields'
                ],
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
                },
                creationStates: 'is-physical'
            },
            getters: {
                ...productStore.getters,
                isLoading: () => false
            }
        });
    });

    beforeEach(async () => {
        await Shopware.State.commit('swProductDetail/setAttributeSet', []);
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show item fields in Measures Packaging card', async () => {
        const wrapper = await createWrapper();

        // expect the some item fields in Packaging is not hidden by css display none
        packagingItemClassName.forEach(item => {
            const inheritedField = wrapper.find('.sw-inherit-wrapper');

            if (!inheritedField.find(item).exists()) {
                return;
            }

            expect(inheritedField.attributes().style).toBeFalsy();
        });
    });

    it('should hide item fields in Measures Packaging card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        // expect the some item fields in Packaging hidden by css display none
        packagingItemClassName.forEach(item => {
            const inheritedField = wrapper.find('.sw-inherit-wrapper');

            if (!inheritedField.find(item).exists()) {
                return;
            }

            expect(inheritedField.attributes().style).toBe('display: none;');
        });
    });

    it('should hide Measures Packaging card when measures_packaging mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Utils.get(wrapper, 'vm.$store.state.swProductDetail.modeSettings');

        await Shopware.State.commit('swProductDetail/setModeSettings', [
            ...modeSettings.filter(item => item !== 'measures_packaging')
        ]);

        expect(wrapper.find('.sw-product-detail-specification__measures-packaging')
            .attributes().style).toBe('display: none;');
    });

    it('should show Properties card even advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        expect(wrapper.find('sw-product-properties-stub').attributes().style).toBeFalsy();
    });

    it('should hide Properties card when properties mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Utils.get(wrapper, 'vm.$store.state.swProductDetail.modeSettings');

        await Shopware.State.commit('swProductDetail/setModeSettings', [
            ...modeSettings.filter(item => item !== 'properties')
        ]);

        expect(wrapper.find('sw-product-properties-stub')
            .attributes().style).toBe('display: none;');
    });

    it('should show Essential Characteristics card when advanced mode is on', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        expect(wrapper.find('.sw-product-detail-specification__essential-characteristics')
            .attributes().style).toBeFalsy();
    });

    it('should hide Essential Characteristics card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        expect(wrapper.find('.sw-product-detail-specification__essential-characteristics')
            .attributes().style).toBe('display: none;');
    });

    it('should hide Essential Characteristics card when essential_characteristics mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Utils.get(wrapper, 'vm.$store.state.swProductDetail.modeSettings');
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        await Shopware.State.commit('swProductDetail/setModeSettings', [
            ...modeSettings.filter(item => item !== 'essential_characteristics')
        ]);

        expect(wrapper.find('sw-product-properties-stub')
            .attributes().style).toBe('display: none;');
    });

    it('should show Custom Fields card advanced mode is on and custom fields set length is greater than 0', async () => {
        const wrapper = await createWrapper();

        await Shopware.State.commit('swProductDetail/setAttributeSet', [{
            customFields: [1, 2]
        }]);

        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');
        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        expect(wrapper.find('.sw-product-detail-specification__custom-fields').attributes().style).toBeFalsy();
    });

    it('should hide Custom Fields card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        expect(wrapper.find('.sw-product-detail-specification__custom-fields')
            .attributes().style).toBe('display: none;');
    });

    it('should hide Custom Fields card when custom_fields mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Utils.get(wrapper, 'vm.$store.state.swProductDetail.modeSettings');
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        await Shopware.State.commit('swProductDetail/setModeSettings', [
            ...modeSettings.filter(item => item !== 'custom_fields')
        ]);

        expect(wrapper.find('.sw-product-detail-specification__custom-fields')
            .attributes().style).toBe('display: none;');
    });

    it('should not show Custom Fields card when custom fields length is smaller than 1', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const customFieldsLength = wrapper.vm.customFieldSets.length;
        expect(customFieldsLength).toBe(0);

        const cardElement = wrapper.find('.sw-product-detail-specification__custom-fields');
        const cardStyles = cardElement.attributes('style');

        expect(cardStyles).toBe('display: none;');
    });

    it('should show measures and packaging card when product states not includes is-download', async () => {
        const wrapper = await createWrapper();

        await Shopware.State.commit('swProductDetail/setProduct', {
            isNew: () => false,
            states: [
                'is-physical'
            ],
        });

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-specification__measures-packaging');
        const cardStyles = cardElement.attributes('style');

        expect(cardStyles).toBe('display: none;');
    });

    it('should not show measures and packaging card when product states includes is-download', async () => {
        const wrapper = await createWrapper();

        await Shopware.State.commit('swProductDetail/setProduct', {
            isNew: () => false,
            states: [
                'is-download'
            ],
        });

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-specification__measures-packaging');
        const cardStyles = cardElement.attributes('style');

        expect(cardStyles).toBe('display: none;');
    });
});
