import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/page/sw-product-detail';
import 'src/module/sw-product/component/sw-product-settings-mode';

const advancedModeSettings = {
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
                name: 'general'
            },
            {
                key: 'prices',
                label: 'sw-product.detailBase.cardTitlePrices',
                enabled: true,
                name: 'general'
            },
            {
                key: 'deliverability',
                label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                enabled: true,
                name: 'general'
            },
            {
                key: 'visibility_structure',
                label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                enabled: true,
                name: 'general'
            },
            {
                key: 'labelling',
                label: 'sw-product.detailBase.cardTitleSettings',
                enabled: true,
                name: 'general'
            }
        ]
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
                $route: {
                    name: 'sw.product.detail.base',
                    params: {
                        id: '1234'
                    }
                }
            },
            provide: {
                numberRangeService: {},
                seoUrlService: {},
                mediaService: {},
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {};
                        },
                        search: () => {
                            return Promise.resolve({});
                        },
                        get: () => {
                            return Promise.resolve({
                                variation: []
                            });
                        }
                    })
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
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>'
                },
                'sw-language-info': true,
                'router-view': true,
                'sw-switch-field': true,
                'sw-context-menu-divider': true,
                'sw-checkbox-field': true,
                'sw-product-settings-mode': Shopware.Component.build('sw-product-settings-mode'),
                'sw-loader': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-inheritance-warning': true,
                'router-link': true
            },
            propsData: {
                productId: '1234'
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

    it('should show advanced mode settings', () => {
        const contextButton = wrapper.find('.sw-product-settings-mode');
        expect(contextButton.exists()).toBe(true);
    });

    it('should show item tabs ', () => {
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
    });

    it('should show item tabs when advanced mode deactivate', async () => {
        wrapper.vm.userModeSettingsRepository.save = jest.fn(() => Promise.resolve());

        await Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSettings.value,
                advancedMode: {
                    enabled: false
                }
            }
        });

        const tabItemClassName = [
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews'
        ];

        tabItemClassName.forEach(item => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });

    it('should show Advance mode setting on the variant product page', async () => {
        await Shopware.State.commit('swProductDetail/setProduct', {
            parentId: '1234'
        });

        const contextButton = wrapper.find('.sw-product-settings-mode');
        expect(contextButton.exists()).toBeFalsy();

        const visibleTabItem = [
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-reviews'
        ];

        const invisibleTabItem = [
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-cross-selling'
        ];

        visibleTabItem.forEach(item => {
            expect(wrapper.find(item).attributes().style).toBeFalsy();
        });

        invisibleTabItem.forEach(item => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });
});
