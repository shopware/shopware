import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/page/sw-product-detail';
import 'src/module/sw-product/component/sw-product-settings-mode';
import 'src/app/component/form/sw-checkbox-field';
import 'src/module/sw-product/component/sw-product-basic-form';
import 'src/app/component/structure/sw-card-view';

const mockSettings = {
    modeSettings: {
        value: {
            advancedMode: {
                label: 'sw-product.general.textAdvancedMode',
                enabled: true
            },
            settings: [
                {
                    key: 'general_information',
                    label: 'sw-product.detailBase.cardTitleProductInfo',
                    enabled: true,
                    tabSetting: 'general'
                },
                {
                    key: 'prices',
                    label: 'sw-product.detailBase.cardTitlePrices',
                    enabled: true,
                    tabSetting: 'general'
                },
                {
                    key: 'deliverability',
                    label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                    enabled: true,
                    tabSetting: 'general'
                },
                {
                    key: 'visibility_structure',
                    label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                    enabled: true,
                    tabSetting: 'general'
                },
                {
                    key: 'labelling',
                    label: 'sw-product.detailBase.cardTitleSettings',
                    enabled: true,
                    tabSetting: 'general'
                }
            ]
        }
    }
};

describe('module/sw-product/page/sw-product-detail', () => {
    function createWrapper() {
        const localVue = createLocalVue();
        localVue.use(Vuex);
        localVue.directive('tooltip', {
            bind(el, binding) {
                el.setAttribute('tooltip-message', binding.value.message);
            }
        });

        return shallowMount(Shopware.Component.build('sw-product-detail'), {
            localVue,
            mocks: {
                $store: Shopware.State._store,
                $route: {
                    name: 'sw.product.detail.base'
                },
                $tc: translationKey => translationKey,
                $device: {
                    getSystemKey: () => {}
                }
            },
            provide: {
                numberRangeService: {},
                seoUrlService: {},
                mediaService: {},
                feature: {
                    isActive: () => true
                },
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        search: async () => {
                            return Promise.resolve(mockSettings);
                        }
                    })
                },
                acl: {
                    can: () => true
                }
            },

            stubs: {
                'sw-page': true,
                'sw-product-variant-info': true,
                'sw-button': true,
                'sw-button-group': true,
                'sw-button-process': true,
                'sw-context-button': true,
                'sw-icon': true,
                'sw-context-menu-item': true,
                'sw-language-switch': true,
                'sw-card-view': Shopware.Component.build('sw-card-view'),
                'sw-language-info': true,
                'router-view': true,
                'sw-switch-field': true,
                'sw-context-menu-divider': true,
                'sw-field-error': true,
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-product-basic-form': Shopware.Component.build('sw-product-basic-form'),
                'sw-base-field': true,
                'sw-product-settings-mode': Shopware.Component.build('sw-product-settings-mode'),
                'sw-sidebar': true,
                'sw-sidebar-media-item': true
            },

            propsData: {
                mockSettings
            }
        });
    }

    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            actions: {
                resetCmsPageState: () => {}
            }
        });
    });

    beforeEach(async () => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show advanced mode settings', async () => {
        wrapper.vm.feature = {
            isActive: () => true
        };

        await wrapper.setProps(mockSettings);

        await wrapper.vm.$nextTick();

        const contextButton = wrapper.find('.sw-product-settings-mode');
        expect(contextButton.exists()).toBe(true);
    });
});
