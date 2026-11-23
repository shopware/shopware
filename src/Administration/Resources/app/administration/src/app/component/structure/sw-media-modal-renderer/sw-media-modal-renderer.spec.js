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
                    'sw-media-modal-v2': {
                        props: ['extensionTabs', 'extensionBaseUrl'],
                        emits: ['media-modal-selection-change', 'modal-close'],
                        template: '<sw-media-modal-v2-stub />',
                    },
                    'sw-media-save-modal': true,
                },
            },
        });

        store = Shopware.Store.get('mediaModal');
    });

    it('should open the media modal', async () => {
        let modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeFalsy();

        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
        });

        await flushPromises();

        modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeTruthy();
    });

    it('should close the media modal', async () => {
        const storeCloseModal = jest.spyOn(store, 'closeModal');

        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
        });

        await flushPromises();

        let modal = wrapper.findComponent('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeTruthy();

        modal.vm.$emit('modal-close');
        expect(storeCloseModal).toHaveBeenCalled();
        await flushPromises();

        modal = wrapper.find('sw-media-modal-v2-stub');
        expect(modal.exists()).toBeFalsy();
    });

    it('should able to get selected media data', async () => {
        const callback = jest.fn();
        store.openModal({
            allowMultiSelect: false,
            fileAccepted: 'image/*',
            selectors: [
                'fileName',
                'fileSize',
                'metaData.width',
                'metaData.height',
            ],
            callback,
        });

        await flushPromises();

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

        await flushPromises();
        expect(callback).toHaveBeenCalledWith([
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

    it('should pass the app tabs of the opening app to the media modal', async () => {
        store.openModal({
            tabs: [{ label: 'Primitives', locationId: 'primitives-tab' }],
            baseUrl: 'https://app.example',
        });
        await flushPromises();

        const modal = wrapper.findComponent('sw-media-modal-v2-stub');
        expect(modal.props('extensionTabs')).toEqual([{ label: 'Primitives', locationId: 'primitives-tab' }]);
        expect(modal.props('extensionBaseUrl')).toBe('https://app.example');
    });

    it('should not pass app tabs when the opening app is unknown', async () => {
        store.openModal({
            tabs: [{ label: 'Primitives', locationId: 'primitives-tab' }],
        });
        await flushPromises();

        expect(wrapper.findComponent('sw-media-modal-v2-stub').props('extensionTabs')).toEqual([]);
    });
});
