/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import SwMediaListSelectionV2 from 'src/app/asyncComponent/media/sw-media-list-selection-v2';
import swMediaListSelectionItemV2 from 'src/app/asyncComponent/media/sw-media-list-selection-item-v2';

Shopware.Component.register('sw-media-list-selection-v2', SwMediaListSelectionV2);
Shopware.Component.register('sw-media-list-selection-item-v2', swMediaListSelectionItemV2);

const entityMediaItems = [
    {
        id: '1',
        url: 'http://shopware.com/image1.jpg',
        position: 3,

    },
    {
        id: '2',
        url: 'http://shopware.com/image2.jpg',
        position: 1,
    },
    {
        id: '3',
        url: 'http://shopware.com/image3.jpg',
        position: 2,
    },
];
async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-media-list-selection-v2'), {

        provide: {
            mediaService: {},
        },
        stubs: {
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-media-list-selection-item-v2': await Shopware.Component.build('sw-media-list-selection-item-v2'),
            'sw-icon': true,
            'sw-media-preview-v2': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
        },
        propsData: {
            entity: {},
            entityMediaItems: entityMediaItems,
        },
    });
}

describe('components/media/sw-media-list-selection-v2', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should set the position property for each item by index in computed', async () => {
        const wrapper = await createWrapper();
        const mediaItems = wrapper.vm.mediaItems;

        mediaItems.forEach((item, index) => {
            expect(item.position).toBe(index);
        });
    });

    it('should emit item-sort event when drag and drop item valid', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onMediaItemDragSort({ id: 2, position: 1 }, { id: 1, position: 2 }, true);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['item-sort']).toBeTruthy();
        expect(wrapper.emitted()['item-sort'][0]).toEqual([{ id: 2, position: 1 }, { id: 1, position: 2 }]);
    });

    it('should not emit item-sort event when drag and drop item valid', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onMediaItemDragSort();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('item-sort')).not.toBeTruthy();
    });
});
