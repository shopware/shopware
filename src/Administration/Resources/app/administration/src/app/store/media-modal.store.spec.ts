/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';

const mediaModalConfig = {
    allowMultiSelect: false,
    fileAccept: 'image/*',
    callback: () => {},
} as const;

const saveMediaModalConfig = {
    initialFolderId: 'folderId',
    initialFileName: 'Media name',
    fileType: 'png',
    callback: () => {},
} as const;

describe('media-modal.store', () => {
    let store = Shopware.Store.get('mediaModal');

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('mediaModal');
    });

    afterEach(() => {
        store.mediaModal = null;
    });

    it('has initial state', () => {
        expect(store.mediaModal).toBeNull();
    });

    it('opens media modal', () => {
        store.openModal(mediaModalConfig);

        expect(store.mediaModal).toStrictEqual(mediaModalConfig);
    });

    it('open save media modal', () => {
        store.openSaveModal(saveMediaModalConfig);

        expect(store.saveMediaModal).toStrictEqual(saveMediaModalConfig);
    });

    it('closes media modal', () => {
        store.openModal(mediaModalConfig);
        expect(store.mediaModal).toStrictEqual(mediaModalConfig);

        store.closeModal();
        expect(store.mediaModal).toBeNull();
    });

    it('closes save media modal', () => {
        store.openSaveModal(saveMediaModalConfig);
        expect(store.saveMediaModal).toStrictEqual(saveMediaModalConfig);

        store.closeSaveModal();
        expect(store.saveMediaModal).toBeNull();
    });
});
