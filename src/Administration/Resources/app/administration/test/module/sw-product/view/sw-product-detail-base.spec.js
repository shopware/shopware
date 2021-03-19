import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/view/sw-product-detail-base';
import 'src/module/sw-product/component/sw-product-basic-form';
import 'src/module/sw-product/component/sw-product-price-form';
import 'src/module/sw-product/component/sw-product-deliverability-form';
import 'src/module/sw-product/component/sw-product-category-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-field';
import Vuex from 'vuex';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-product-detail-base'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-product-detail-base__review-card': true,
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-product-packaging-form': true,
            'sw-product-seo-form': true,
            'sw-product-category-form': Shopware.Component.build('sw-product-category-form'),
            'sw-product-deliverability-form': Shopware.Component.build('sw-product-deliverability-form'),
            'sw-product-price-form': Shopware.Component.build('sw-product-price-form'),
            'sw-product-basic-form': Shopware.Component.build('sw-product-basic-form'),
            'sw-product-feature-set-form': true,
            'sw-product-settings-form': true,
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-empty-state': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-media-modal-v2': true,
            'sw-container': true,
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-editor': true,
            'sw-switch-field': true,
            'sw-product-media-form': true,
            'sw-entity-single-select': true,
            'sw-help-text': true,
            'sw-price-field': true,
            'sw-list-price-field': true,
            'sw-icon': true,
            'sw-number-field': true,
            'sw-text-field': true,
            'sw-select-field': true,
            'sw-product-visibility-select': true,
            'sw-category-tree-field': true,
            'sw-multi-tag-select': true,
            'sw-entity-tag-select': true
        },
        mocks: {
            $tc: snippetPath => snippetPath,
            $store: Shopware.State._store,
            $route: {
                name: 'sw.product.detail.base',
                params: {
                    id: '1234'
                }
            },
            $device: {
                getSystemKey: () => {}
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve('bar'),
                    searchIds: () => Promise.resolve()
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => false
            }
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-base', () => {
    Shopware.State.registerModule('swProductDetail', productStore);

    beforeAll(() => {
        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            getters: {
                isLoading: () => false
            },
            state: {
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
                    }]
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
                    'labelling',
                    'measures_packaging',
                    'properties',
                    'essential_characteristics',
                    'custom_fields'
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
            },
            mutations: {
                setLoading: () => true
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get media default folder id when component got created', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.getMediaDefaultFolderId = jest.fn(() => {
            return Promise.resolve(Shopware.Utils.createId());
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getMediaDefaultFolderId).toHaveBeenCalledTimes(1);
        wrapper.vm.getMediaDefaultFolderId.mockRestore();
    });

    it('should turn on media modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            showMediaModal: true
        });

        const mediaModal = wrapper.find('sw-media-modal-v2-stub');

        expect(mediaModal.exists()).toBeTruthy();
        expect(mediaModal.attributes('entitycontext')).toBe('product');
    });

    it('should turn off media modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            showMediaModal: false
        });

        const mediaModal = wrapper.find('sw-media-modal-v2-stub');

        expect(mediaModal.exists()).toBeFalsy();
    });

    it('should be able to add a new media', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.addMedia = jest.fn(() => Promise.resolve());

        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };
        await wrapper.vm.onAddMedia([media]);
        await wrapper.setData({
            product: {
                media: [
                    media
                ]
            }
        });

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(media);
        expect(wrapper.vm.product.media).toEqual(expect.arrayContaining([media]));

        wrapper.vm.addMedia.mockRestore();
    });

    it('should not be able to add a new media', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };

        wrapper.vm.addMedia = jest.fn(() => Promise.reject(media));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onAddMedia([media]);

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(media);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-product.mediaForm.errorMediaItemDuplicated'
        });

        wrapper.vm.addMedia.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should set media as cover', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };

        await wrapper.vm.setMediaAsCover(media);

        expect(wrapper.vm.product.coverId).toBe(media.id);
    });

    it('should be visible Promotion Switch ', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];
        const promotionSwitch = wrapper.find('.sw-product-basic-form__promotion-switch');

        expect(promotionSwitch.exists()).toBe(true);
        expect(showModeSetting).toBe(true);
    });

    it('should be visible Labelling card', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];
        const labellingCardElement = wrapper.find('.sw-product-detail-base__labelling-card');

        expect(labellingCardElement.exists()).toBe(true);
        expect(showModeSetting).toBe(true);
    });

    it('should be visible Media card', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        const mediaCardElement = wrapper.find('.sw-product-detail-base__media');

        await wrapper.vm.$nextTick(() => {
            expect(mediaCardElement.exists()).toBe(true);
            expect(showProductCard('media')).toBe(true);
        });
    });

    it('should be visible price item fields', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];
        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-price-field.sw-list-price-field__list-price'
        ];

        await wrapper.vm.$nextTick(() => {
            priceFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(true);
            });
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be visible Deliverability item fields', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];
        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        await wrapper.vm.$nextTick(() => {
            deliveryFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(true);
            });
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be visible Structure item fields', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showModeSetting = wrapper.vm.$store.getters['swProductDetail/showModeSetting'];
        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        await wrapper.vm.$nextTick(() => {
            structureFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(true);
            });
            expect(showModeSetting).toBe(true);
        });
    });

    it('should be not visible Promotion Switch when commit setAdvancedModeSetting with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };

        Shopware.State.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                settings: [
                    {
                        key: 'general_information',
                        label: 'sw-product.detailBase.cardTitleProductInfo',
                        enabled: false,
                        name: 'general'
                    }
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];


        wrapper.vm.$nextTick(() => {
            const promotionSwitch = wrapper.find('.sw-product-basic-form__promotion-switch');

            expect(promotionSwitch.attributes().style).toBe('display: none;');
            expect(showProductCard('general_information')).toBe(false);
        });
    });

    it('should be not visible Labelling card when commit setModeSettings with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };

        Shopware.State.commit('swProductDetail/setModeSettings', []);
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        await wrapper.vm.$nextTick();
        const labellingCardElement = wrapper.find('.sw-product-detail-base__labelling-card');

        expect(labellingCardElement.attributes().style).toBe('display: none;');
        expect(showProductCard('labelling')).toBe(false);
    });

    it('should be not visible price item fields when commit setModeSettings with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-price-field.sw-list-price-field__list-price'
        ];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            priceFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(false);
            });
            expect(showProductCard('prices')).toBe(false);
        });
    });

    it('should be not visible Deliverability item fields when commit setModeSettings with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            deliveryFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(false);
            });
            expect(showProductCard('deliverability')).toBe(false);
        });
    });

    it('should be not visible Structure item fields when commit setAdvancedModeSetting with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };
        await wrapper.vm.$nextTick();
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        Shopware.State.commit('swProductDetail/setModeSettings', []);

        await wrapper.vm.$nextTick(() => {
            structureFieldsClassName.forEach(item => {
                expect(wrapper.find(item).exists()).toBe(false);
            });
            expect(showProductCard('visibility_structure')).toBe(false);
        });
    });

    it('should be not visible Media card when commit setModeSettings with falsy value', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.feature = {
            isActive: () => true
        };

        Shopware.State.commit('swProductDetail/setModeSettings', []);
        const showProductCard = wrapper.vm.$store.getters['swProductDetail/showProductCard'];
        await wrapper.vm.$nextTick();
        const mediaCardElement = wrapper.find('.sw-product-detail-base__media');

        expect(mediaCardElement.attributes().style).toBe('display: none;');
        expect(showProductCard('media')).toBe(false);
    });
});
