/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';

const rootFolderObject = {
    id: null,
    name: 'sw-media.index.rootFolderName',
};

const createMediaEntity = (options = {}) => {
    return new Entity(Shopware.Utils.createId(), 'media', {
        fileName: 'test.png',
        ...options,
    });
};

const createFolderEntity = (options = {}) => {
    return new Entity(Shopware.Utils.createId(), 'media_folder', {
        name: 'test',
        parentId: null,
        ...options,
    });
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-media-modal-move', { sync: true }), {
        props: {
            itemsToMove: [createMediaEntity()],
        },
        global: {
            stubs: {
                'sw-icon': true,
                'sw-media-folder-content': true,
                'sw-button': true,
            },
        },
    });
}

describe('components/media/sw-media-modal-move', () => {
    it('removes parent folder if current folder is root folder', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            parentFolder: createFolderEntity(),
        });
        wrapper.vm.fetchParentFolder = jest.fn();

        await wrapper.vm.updateParentFolder(rootFolderObject);
        expect(wrapper.vm.fetchParentFolder).not.toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toBeNull();
    });

    it('correctly uses root folder as parent folder', async () => {
        const wrapper = await createWrapper();

        const childFolder = createFolderEntity({ parentId: null });
        wrapper.vm.fetchParentFolder = jest.fn();

        await wrapper.vm.updateParentFolder(childFolder);
        expect(wrapper.vm.fetchParentFolder).not.toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toMatchObject(rootFolderObject);
    });

    it('fetches parent folder when a parentId is given', async () => {
        const wrapper = await createWrapper();

        const mockedParent = createFolderEntity();
        const mockedChild = createFolderEntity({ parentId: mockedParent.id });

        wrapper.vm.mediaFolderRepository.search = jest.fn(() => Promise.resolve([
            mockedParent,
        ]));

        await wrapper.vm.updateParentFolder(mockedChild);

        expect(wrapper.vm.mediaFolderRepository.search).toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toMatchObject(mockedParent);
    });

    it('handles fetchParentFolder Admin API error gracefully', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.mediaFolderRepository.search = jest.fn(Promise.reject);

        await wrapper.vm.fetchParentFolder(Shopware.Utils.createId());

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });
});
