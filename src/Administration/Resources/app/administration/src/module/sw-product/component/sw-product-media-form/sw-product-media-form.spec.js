/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductMediaForm from 'src/module/sw-product/component/sw-product-media-form';
import 'src/app/component/base/sw-product-image';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/utils/sw-popover';

import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-product-media-form', swProductMediaForm);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});
    localVue.directive('popover', {});

    return shallowMount(await Shopware.Component.build('sw-product-media-form'), {
        localVue,
        mocks: {
            $store: new Vuex.Store({
                modules: {
                    swProductDetail: {
                        namespaced: true,
                        getters: {
                            isLoading: () => false,
                        },
                    },
                },
            }),
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },

        },
        stubs: {
            'sw-upload-listener': true,
            'sw-product-image': await Shopware.Component.build('sw-product-image'),
            'sw-media-upload-v2': true,
            'sw-media-preview-v2': true,
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-icon': true,
            'sw-label': true,
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
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
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should only show 1 cover', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);

        let coverCount = 0;
        wrapper.vm.mediaItems.forEach(mediaItem => {
            if (wrapper.vm.isCover(mediaItem)) {
                coverCount += 1;
            }
        });

        expect(coverCount).toBe(1);
    });

    it('should emit an event when onOpenMedia() function is called', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should can show cover when `showCoverLabel` is true', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeTruthy();
    });

    it('should not show cover when `showCoverLabel` is false', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showCoverLabel: false,
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-product-media-form__previews').find('.sw-product-image__context-button').trigger('click');
        await wrapper.vm.$nextTick();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons).toHaveLength(1);
        expect(buttons.at(0).text()).toContain('Remove');
    });

    it('should move media to first position when it is marked as cover', async () => {
        const wrapper = await createWrapper();

        let productMediaItems = wrapper.findAll('.sw-product-image');

        expect(productMediaItems.wrappers[0].classes()).toContain('is--cover');
        expect(productMediaItems.wrappers[0].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[0].mediaId);
        expect(productMediaItems.wrappers[1].classes()).not.toContain('is--cover');
        expect(productMediaItems.wrappers[1].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[1].mediaId);

        const contextButton = productMediaItems.wrappers[1].find('.sw-product-image__context-button');
        await contextButton.trigger('click');

        const buttonCover = contextButton.find('.sw-product-image__button-cover');
        expect(buttonCover.exists()).toBeTruthy();

        // Media will be move to first position after clicking on Use as cover button
        await buttonCover.trigger('click');

        productMediaItems = wrapper.findAll('.sw-product-image');
        expect(productMediaItems.wrappers[0].classes()).toContain('is--cover');
        expect(productMediaItems.wrappers[0].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[1].mediaId);

        expect(productMediaItems.wrappers[1].classes()).not.toContain('is--cover');
        expect(productMediaItems.wrappers[1].find('sw-media-preview-v2-stub')
            .attributes('source')).toEqual(media[0].mediaId);
    });
});
