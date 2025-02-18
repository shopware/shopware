/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { nextTick } from 'vue';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-detail-base', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-product-detail-base__review-card': true,
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`,
                },
                'sw-product-category-form': true,
                'sw-product-deliverability-form': true,
                'sw-product-deliverability-downloadable-form': true,
                'sw-product-download-form': true,
                'sw-product-price-form': true,
                'sw-product-basic-form': await wrapTestComponent('sw-product-basic-form', { sync: true }),
                'sw-product-feature-set-form': true,
                'sw-product-settings-form': true,
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
                'sw-empty-state': true,
                'sw-card': {
                    template: '<div><slot></slot><slot name="title"></slot><slot name="grid"></slot></div>',
                },
                'sw-context-menu-item': true,
                'sw-media-modal-v2': true,
                'sw-container': true,
                'sw-text-editor': true,
                'sw-switch-field': true,
                'sw-product-media-form': true,
                'sw-entity-single-select': true,
                'sw-help-text': true,
                'sw-icon': {
                    template: '<div class="sw-icon" @click="$emit(\'click\')"></div>',
                },
                'sw-text-field': true,
                'sw-select-field': true,
                'router-link': true,
                'sw-skeleton': true,
                'sw-extension-teaser-popover': true,
                'mt-card': true,
                'mt-text-editor': true,
            },
            mocks: {
                $route: {
                    name: 'sw.product.detail.base',
                    params: {
                        id: '1234',
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () =>
                            Promise.resolve({
                                first: () => {
                                    return {
                                        folder: {},
                                    };
                                },
                            }),
                        get: () => Promise.resolve({}),
                        searchIds: () =>
                            Promise.resolve({
                                data: [],
                            }),
                        create: () => ({ id: 'id' }),
                    }),
                },
            },
        },
    });
}

describe('src/module/sw-product/view/sw-product-detail-base', () => {
    beforeEach(() => {
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = {
            media: [],
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
        };
        store.product = {
            id: 'productId',
            getEntityName: () => 'product',
            isNew: () => false,
            media: new EntityCollection('', '', {}, {}, []),
            coverId: null,
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
            purchasePrices: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            ],
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 100,
                    net: 84.034,
                },
            ],
        };
        store.modeSettings = [
            'general_information',
            'prices',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
        ];
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'general_information',
                        label: 'sw-product.detailBase.cardTitleProductInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'prices',
                        label: 'sw-product.detailBase.cardTitlePrices',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'deliverability',
                        label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'visibility_structure',
                        label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'labelling',
                        label: 'sw-product.detailBase.cardTitleSettings',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        store.creationStates = 'is-physical';
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show files card when product states not includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            states: [
                'is-physical',
            ],
        };

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-base__downloads');

        expect(cardElement.exists()).toBeFalsy();
    });

    it('should show files card when product states includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            states: [
                'is-download',
            ],
        };

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-base__downloads');
        expect(cardElement).toBeTruthy();
    });

    it('should show correct deliverability card when product states includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            states: [
                'is-download',
            ],
        };

        await wrapper.vm.$nextTick();

        const physicalCardElement = wrapper.find('.sw-product-detail-base__deliverability');
        expect(physicalCardElement.exists()).toBeFalsy();

        const cardElement = wrapper.find('.sw-product-detail-base__deliverability-downloadable');
        expect(cardElement).toBeTruthy();

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            states: [
                'is-physical',
            ],
        };
    });

    it('should get media default folder id when component got created', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getMediaDefaultFolderId = jest.fn(() => {
            return Promise.resolve('SOME-ID');
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getMediaDefaultFolderId).toHaveBeenCalledTimes(1);
        wrapper.vm.getMediaDefaultFolderId.mockRestore();
    });

    it('should able to open media modal', async () => {
        const wrapper = await createWrapper();

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');

        expect(mediaModal.exists()).toBe(true);
        expect(mediaModal.attributes('entity-context')).toBe('product');
    });

    it('should able to close media modal', async () => {
        const wrapper = await createWrapper();

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('modal-close');

        expect(mediaModal.exists()).toBe(false);
    });

    it('should not be able to add a null media', async () => {
        const wrapper = await createWrapper();

        const spyOnAddMedia = jest.spyOn(wrapper.vm, 'addMedia');

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('media-modal-selection-change', null);

        expect(spyOnAddMedia).not.toHaveBeenCalled();
    });

    it('should be able to add a new media', async () => {
        const wrapper = await createWrapper();

        const media = {
            id: 'id',
            fileName: 'fileName',
            fileSize: 101,
            url: 'http://image.jpg',
        };

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('media-modal-selection-change', [media]);

        expect(wrapper.vm.product.media).toEqual(
            expect.arrayContaining([
                {
                    id: 'id',
                    media: {
                        id: 'id',
                        url: 'http://image.jpg',
                    },
                    mediaId: 'id',
                    position: 0,
                },
            ]),
        );
    });

    it('should not be able to add a new media', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();

        const media = {
            id: 'id',
            fileName: 'fileName',
            fileSize: 101,
            url: 'http://image.jpg',
        };

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            media: new EntityCollection('', '', {}, {}, [
                {
                    id: 'id',
                    media: {
                        id: 'id',
                        url: 'http://image.jpg',
                    },
                    mediaId: 'id',
                    position: 0,
                },
            ]),
        };

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('media-modal-selection-change', [media]);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-product.mediaForm.errorMediaItemDuplicated',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should set media as cover when product media is empty and media is not glb file', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('media-modal-selection-change', [media]);

        expect(wrapper.vm.product.coverId).toBe(media.id);
    });

    it('should show Promotion Switch of General card when advanced mode is on', async () => {
        const wrapper = await createWrapper();

        const promotionSwitch = wrapper.find('.sw-product-basic-form__promotion-switch');
        expect(promotionSwitch.attributes().style).toBeFalsy();
    });

    it('should show Labelling card when advanced mode is on', async () => {
        const wrapper = await createWrapper();

        const labellingCardElement = wrapper.find('.sw-product-detail-base__labelling-card');
        expect(labellingCardElement.attributes().style).toBeFalsy();
    });

    it('should show Media card when media mode is checked', async () => {
        const wrapper = await createWrapper();

        const mediaCardElement = wrapper.find('.sw-product-detail-base__media');
        expect(mediaCardElement.attributes().style).toBeFalsy();
    });

    it('should hide Promotion Switch when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        await wrapper.vm.$nextTick();

        const promotionSwitch = wrapper.find('.sw-product-basic-form__promotion-switch');
        expect(promotionSwitch.attributes().style).toBe('display: none;');
    });

    it('should hide Labelling card when commit when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        await wrapper.vm.$nextTick();

        const labellingCardElement = wrapper.find('.sw-product-detail-base__labelling-card');
        expect(labellingCardElement.attributes().style).toBe('display: none;');
    });

    it('should hide Media card when media mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'media'),
        ];

        await wrapper.vm.$nextTick();

        const mediaCardElement = wrapper.find('.sw-product-detail-base__media');
        expect(mediaCardElement.attributes().style).toBe('display: none;');
    });

    it('should hide General card when general_information mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'general_information'),
        ];

        await wrapper.vm.$nextTick();

        const infoCardElement = wrapper.find('.sw-product-detail-base__info');
        expect(infoCardElement.attributes().style).toBe('display: none;');
    });

    it('should hide Prices card when prices mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'prices'),
        ];

        await wrapper.vm.$nextTick();

        const infoCardElement = wrapper.find('.sw-product-detail-base__prices');
        expect(infoCardElement.attributes().style).toBe('display: none;');
    });

    it('should hide Deliverability card when deliverability mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'deliverability'),
        ];

        await wrapper.vm.$nextTick();

        const infoCardElement = wrapper.find('.sw-product-detail-base__deliverability');
        expect(infoCardElement.attributes().style).toBe('display: none;');
    });

    it('should hide Visibility Structure card when prices mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'visibility_structure'),
        ];

        await wrapper.vm.$nextTick();

        const infoCardElement = wrapper.find('.sw-product-detail-base__visibility-structure');
        expect(infoCardElement.attributes().style).toBe('display: none;');
    });

    it('should not set media cover when adding new glb file', async () => {
        const wrapper = await createWrapper();

        const productMediaFrom = wrapper.findComponent('sw-product-media-form-stub');
        await productMediaFrom.vm.$emit('media-open');

        const media = {
            id: '3dFileId',
            fileName: '3DFile',
            fileSize: 101,
            url: 'htt://example.com/3dfile.glb',
        };

        const mediaModal = wrapper.findComponent('sw-media-modal-v2-stub');
        await mediaModal.vm.$emit('media-modal-selection-change', [media]);

        expect(wrapper.vm.product.coverId).toBeNull();
    });

    it('should able to toggle off media inheritance', async () => {
        const wrapper = await createWrapper();
        const media = {
            id: 'id',
            media: {
                id: 'id',
                url: 'http://image.jpg',
            },
            mediaId: 'id',
            position: 0,
        };

        Shopware.Store.get('swProductDetail').parentProduct = {
            ...Shopware.Store.get('swProductDetail').parentProduct,
            media: new EntityCollection('', '', {}, {}, [media]),
        };

        await nextTick();

        expect(wrapper.vm.product.media).toHaveLength(0);

        const inheritanceSwitch = wrapper.find('.sw-inheritance-switch--is-inherited .sw-icon');
        expect(inheritanceSwitch.exists()).toBe(true);

        await inheritanceSwitch.trigger('click');

        expect(wrapper.vm.product.media.first()).toEqual({
            id: media.id,
            position: media.position,
            mediaId: media.mediaId,
            productId: 'productId',
        });
    });

    it('should able to toggle on media inheritance', async () => {
        const wrapper = await createWrapper();

        const media = {
            id: 'id',
            media: {
                id: 'id',
                url: 'http://image.jpg',
            },
            mediaId: 'id',
            position: 0,
        };

        Shopware.Store.get('swProductDetail').parentProduct = {
            ...Shopware.Store.get('swProductDetail').parentProduct,
            media: new EntityCollection('', '', {}, {}, [media]),
        };

        const media1 = {
            ...media,
            id: 'id1',
            media: {
                id: 'id1',
                url: 'http://image1.jpg',
            },
            mediaId: 'id1',
        };

        Shopware.Store.get('swProductDetail').product = {
            ...Shopware.Store.get('swProductDetail').product,
            media: new EntityCollection('', '', {}, {}, [media1]),
        };

        await nextTick();

        expect(wrapper.vm.product.media.first()).toEqual(media1);

        const notInheritanceSwitch = wrapper.find('.sw-inheritance-switch--is-not-inherited .sw-icon');
        expect(notInheritanceSwitch.exists()).toBe(true);

        await notInheritanceSwitch.trigger('click');

        expect(wrapper.vm.product.media).toHaveLength(0);
    });
});
