/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import EntityCollection from 'src/core/data/entity-collection.data';

async function createWrapper() {
    const store = createStore({
        modules: {
            swProductDetail: {
                namespaced: true,
                getters: {
                    isLoading: () => false,
                },
            },
        },
    });

    return mount(await wrapTestComponent('sw-product-media-form', { sync: true }), {
        attachTo: document.body,
        global: {
            plugins: [store],
            directives: {
                draggable: {},
                droppable: {},
                popover: {},
            },
            mocks: {
                $store: store,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        create: () => {
                            if (entity === 'product_media') {
                                return {
                                    id: 'productMedia1',
                                    mediaId: 'media1',
                                };
                            }

                            return undefined;
                        },
                        get: () => {
                            if (entity === 'media') {
                                return Promise.resolve({
                                    id: 'media1',
                                    url: 'http://shopware.test/media1-new-url.jpg',
                                });
                            }

                            return Promise.resolve(undefined);
                        },
                    }),
                },
            },
            stubs: {
                'sw-upload-listener': true,
                'sw-product-image': await wrapTestComponent('sw-product-image'),
                'sw-media-upload-v2': true,
                'sw-media-preview-v2': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-icon': true,
                'sw-label': true,
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
            },
        },
    });
}

const media = [
    {
        mediaId: 'media1',
        position: 0,
        id: 'productMedia1',
        media: {
            id: 'media1',
        },
    },
    {
        mediaId: 'media2',
        position: 1,
        id: 'productMedia2',
        media: {
            id: 'media2',
        },
    },
];

function getMediaCollection(collection = []) {
    return new EntityCollection(
        '/media',
        'media',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

describe('module/sw-product/component/sw-product-media-form', () => {
    beforeAll(() => {
        const product = {
            cover: {
                mediaId: 'media1',
                position: 1,
                id: 'productMedia1',
                media: {
                    id: 'media1',
                },
            },
            coverId: 'productMedia1',
            media: getMediaCollection(media),
        };
        product.getEntityName = () => 'T-Shirt';

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: product,
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should only show 1 cover', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        let coverCount = 0;
        wrapper.vm.mediaItems.forEach(mediaItem => {
            if (wrapper.vm.isCover(mediaItem)) {
                coverCount += 1;
            }
        });

        expect(coverCount).toBe(1);
    });

    it('should emit an event when onOpenMedia() function is called', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should can show cover when `showCoverLabel` is true', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeTruthy();
    });

    it('should not show cover when `showCoverLabel` is false', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            showCoverLabel: false,
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-product-media-form__previews').find('.sw-product-image__context-button').trigger('click');
        await flushPromises();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons).toHaveLength(1);
        expect(buttons.at(0).text()).toContain('Remove');
    });

    it('should move media to first position when it is marked as cover', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        let productMediaItems = wrapper.findAll('.sw-product-image');

        expect(productMediaItems[0].classes()).toContain('is--cover');
        expect(productMediaItems[0].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[0].mediaId);
        expect(productMediaItems[1].classes()).not.toContain('is--cover');
        expect(productMediaItems[1].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[1].mediaId);

        const contextButton = productMediaItems[1].find('.sw-product-image__context-button');
        await contextButton.trigger('click');
        await flushPromises();

        const buttonCover = contextButton.find('.sw-product-image__button-cover');
        expect(buttonCover.exists()).toBeTruthy();

        // Media will be move to first position after clicking on Use as cover button
        await buttonCover.trigger('click');

        productMediaItems = wrapper.findAll('.sw-product-image');
        expect(productMediaItems[0].classes()).toContain('is--cover');
        expect(productMediaItems[0].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[1].mediaId);

        expect(productMediaItems[1].classes()).not.toContain('is--cover');
        expect(productMediaItems[1].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[0].mediaId);
    });

    it('should remove previous mediaItem if it already exists after upload', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        // Check if previous mediaItem exists
        expect(wrapper.vm.product.media).toHaveLength(2);
        expect(wrapper.vm.product.media[1].mediaId).toBe('media1');

        // Simulate successful upload for existing media
        await wrapper.vm.successfulUpload({ targetId: 'media1' });

        // Check if previous mediaItem is removed and new mediaItem is added
        expect(wrapper.vm.product.media).toHaveLength(2);
        expect(wrapper.vm.product.media[1].mediaId).toBe('media1');
        // Check if new mediaItem has new url
        expect(wrapper.vm.product.media[1].media.url).toBe('http://shopware.test/media1-new-url.jpg');
    });
});
