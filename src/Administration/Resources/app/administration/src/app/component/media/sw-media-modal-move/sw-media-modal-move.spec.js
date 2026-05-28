/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';

const mockSnackbar = {
    addSnackbar: jest.fn(),
};

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: () => mockSnackbar,
}));

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

let repositoryFactoryMock;
async function createWrapper() {
    repositoryFactoryMock = {
        save: jest.fn((item) => Promise.resolve(item.id)),
        search: jest.fn(() => Promise.resolve([])),
    };

    return mount(await wrapTestComponent('sw-media-modal-move', { sync: true }), {
        props: {
            itemsToMove: [createMediaEntity()],
        },
        global: {
            stubs: {
                'sw-media-folder-content': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => repositoryFactoryMock,
                },
            },
        },
    });
}

describe('components/media/sw-media-modal-move', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

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

        repositoryFactoryMock.search = jest.fn(() =>
            Promise.resolve([
                mockedParent,
            ]),
        );

        await wrapper.vm.updateParentFolder(mockedChild);

        expect(repositoryFactoryMock.search).toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toMatchObject(mockedParent);
    });

    it('handles fetchParentFolder Admin API error gracefully', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.mediaFolderRepository.search = jest.fn(Promise.reject);

        await wrapper.vm.fetchParentFolder(Shopware.Utils.createId());

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should show a snackbar after moving media items successfully', async () => {
        const mediaId = Shopware.Utils.createId();
        const wrapper = await createWrapper();

        await wrapper.setProps({
            itemsToMove: [
                createMediaEntity({ id: mediaId }),
            ],
        });
        await wrapper.setData({
            targetFolder: {
                id: Shopware.Utils.createId(),
            },
        });
        wrapper.vm.createNotificationSuccess = jest.fn();

        await wrapper.vm.moveSelection();

        expect(mockSnackbar.addSnackbar).toHaveBeenCalledWith({
            variant: 'success',
            message: 'global.sw-media-modal-move.notification.successOverall.message',
        });
        expect(wrapper.vm.createNotificationSuccess).not.toHaveBeenCalled();
    });
});
