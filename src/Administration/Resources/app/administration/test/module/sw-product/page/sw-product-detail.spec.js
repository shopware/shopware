import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/page/sw-product-detail';
import 'src/module/sw-product/component/sw-product-settings-mode';
import 'src/app/component/form/sw-checkbox-field';
import 'src/module/sw-product/component/sw-product-basic-form';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/form/sw-switch-field';

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
                    name: 'sw.product.detail.base',
                    params: {
                        id: '1234'
                    }
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
                        },
                        get: async () => {
                            return Promise.resolve({
                                variation: []
                            });
                        }
                    })
                },
                acl: {
                    can: () => true
                }
            },

            stubs: {
                'sw-page': {
                    template:
                        `<div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">
                                <div class="sw-tabs"></div>
                            </slot>
                            <slot></slot>
                        </div>`
                },
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
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-context-menu-divider': true,
                'sw-field-error': true,
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-product-basic-form': Shopware.Component.build('sw-product-basic-form'),
                'sw-base-field': true,
                'sw-product-settings-mode': Shopware.Component.build('sw-product-settings-mode'),
                'sw-sidebar': true,
                'sw-sidebar-media-item': true,
                'sw-loader': true,
                'sw-tabs': true,
                'sw-tabs-item': true
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

    it('should be visible item tabs ', async () => {
        wrapper.vm.feature = {
            isActive: () => true
        };

        await wrapper.setProps({
            ...mockSettings,
            productId: '1234'
        });

        const tabItemClassName = [
            '.sw-product-detail__tab-advanced-prices',
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews'
        ];

        tabItemClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
        expect(wrapper.vm.$store.getters['swProductDetail/showModeSetting']).toBe(true);
    });

    it('should be not visible item tabs when advanced mode deactivate', async () => {
        wrapper.vm.feature = {
            isActive: () => true
        };
        wrapper.vm.userModeSettingsRepository.save = jest.fn(() => Promise.resolve());

        await wrapper.setProps({
            ...mockSettings,
            productId: '1234'
        });

        Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                advancedMode: {
                    enabled: false
                },
                settings: []
            }
        });

        const tabItemClassName = [
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews'
        ];
        await wrapper.vm.$nextTick(() => {
            tabItemClassName.forEach(item => {
                expect(wrapper.find(item).attributes().style).toBe('display: none;');
            });
        });
        expect(wrapper.vm.$store.getters['swProductDetail/showModeSetting']).toBe(false);
    });
});
