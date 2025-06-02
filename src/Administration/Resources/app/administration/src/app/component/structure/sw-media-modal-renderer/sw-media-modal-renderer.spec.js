/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-media-modal-renderer';

describe('src/app/component/structure/sw-media-modal-renderer', () => {
    let wrapper = null;
    let store = Shopware.Store.get('mediaModal');

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-media-modal-renderer', { sync: true }), {
            global: {
                stubs: {
                    'sw-media-modal-v2': true,
                },
            },
        });

        store = Shopware.Store.get('mediaModal');
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the media modal', async () => {
        let modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeFalsy();

        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
        });

        await wrapper.vm.$nextTick();

        modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeTruthy();
    });

    it('should close the media modal', async () => {
        const storeCloseModal = jest.spyOn(store, 'closeModal');

        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
        });

        await wrapper.vm.$nextTick();

        let modal = wrapper.findComponent('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeTruthy();

        modal.vm.$emit('modal-close');
        expect(storeCloseModal).toHaveBeenCalled();
        await wrapper.vm.$nextTick();

        modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeFalsy();
    });

    it('should able to get selected media data', async () => {
        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
            selectors: [
                'fileName',
                'fileSize',
                'metaData.width',
                'metaData.height',
            ],
            callback: jest.fn(),
        });

        await wrapper.vm.$nextTick();

        const modal = wrapper.findComponent('sw-media-modal-v2-stub');
        modal.vm.$emit('media-modal-selection-change', [
            {
                fileName: 'test.jpg',
                fileSize: 123456,
                mimeType: 'image/jpeg',
                url: 'https://example.com/test.jpg',
                id: 'media-id',
                metaData: {
                    width: 100,
                    height: 100,
                },
            },
        ]);

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.mediaModal.callback).toHaveBeenCalledWith([
            {
                fileName: 'test.jpg',
                fileSize: 123456,
                metaData: {
                    width: 100,
                    height: 100,
                },
            },
        ]);
    });
});
