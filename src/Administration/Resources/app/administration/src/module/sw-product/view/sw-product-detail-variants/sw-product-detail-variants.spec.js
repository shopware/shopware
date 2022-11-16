/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDetailVariants from 'src/module/sw-product/view/sw-product-detail-variants';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

const { Component } = Shopware;

Shopware.Component.register('sw-product-detail-variants', swProductDetailVariants);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Component.build('sw-product-detail-variants'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([]);
                    },
                    delete: () => {
                        return Promise.resolve();
                    },
                    get: () => {
                        return Promise.resolve({
                            configuratorSettings: [
                                {
                                    option: {
                                        groupId: 1,
                                    },
                                },
                            ],
                        });
                    }
                })
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
        mocks: {
            $tc: key => key
        },
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div class="sw-data-grid">
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
            'sw-context-menu-item': true,
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-modal': true,
            'sw-skeleton': true,
            'sw-product-variants-overview': true,
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-reviews', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swProductDetail', {
            ...productStore,
            state: {
                ...productStore.state,
                parentProduct: {
                    media: [],
                    reviews: [{
                        id: '1a2b3c',
                        entity: 'review',
                        customerId: 'd4c3b2a1',
                        productId: 'd4c3b2a1',
                        salesChannelId: 'd4c3b2a1'
                    }]
                },
                product: {
                    getEntityName: () => 'product',
                    media: [],
                    reviews: [{
                        id: '1a2b3c',
                        entity: 'review',
                        customerId: 'd4c3b2a1',
                        productId: 'd4c3b2a1',
                        salesChannelId: 'd4c3b2a1'
                    }],
                    purchasePrices: [{
                        currencyId: '1',
                        linked: true,
                        gross: 0,
                        net: 0
                    }],
                    price: [{
                        currencyId: '1',
                        linked: true,
                        gross: 100,
                        net: 84.034
                    }],
                    configuratorSettings: [],
                    children: [],
                },
                loading: {
                    product: false,
                    media: false
                },
                modeSettings: [
                    'general_information',
                    'prices',
                    'deliverability',
                    'visibility_structure',
                    'media',
                    'labelling'
                ],
                advancedModeSetting: {
                    value: {
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
                        ],
                        advancedMode: {
                            enabled: true,
                            label: 'sw-product.general.textAdvancedMode'
                        }
                    }
                }
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display a customized empty state if there are neither variants nor properties', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            groups: [{}],
            isLoading: false,
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.sw-empty-state__title')
            .text()).toBe('sw-product.variations.emptyStatePropertyTitle');
        expect(wrapper.find('.sw-empty-state__description-content').text())
            .toBe('sw-product.variations.emptyStatePropertyDescription');
    });
});
