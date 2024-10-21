/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper(mediaServiceFunctions = {}) {
    return mount(await wrapTestComponent('sw-media-media-item', { sync: true }), {
        global: {
            provide: {
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    ...mediaServiceFunctions,
                },
            },
            stubs: {
                'sw-media-base-item': true,
                'sw-media-preview-v2': true,
                'sw-text-field': true,
                'sw-context-menu-item': true,
                'sw-media-modal-replace': true,
                'sw-media-modal-delete': true,
                'sw-media-modal-move': true,
            },
        },
    });
}

describe('components/media/sw-media-media-item', () => {
    it('should throw error if new file name is too long', async () => {
        global.activeAclRoles = ['media.editor'];
        const error = {
            status: 400,
            code: 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG',
            meta: {
                parameters: {
                    length: 255,
                },
            },
        };

        const wrapper = await createWrapper({
            renameMedia: () =>
                // eslint-disable-next-line prefer-promise-reject-errors
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                error,
                            ],
                        },
                    },
                }),
        });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName(
            'new file name',
            {
                isLoading: false,
            },
            () => {},
        );

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.fileNameTooLong.message',
        });
    });

    it('should throw general renaming error as fallback', async () => {
        global.activeAclRoles = ['media.editor'];
        const error = {
            status: 400,
            code: 'CONTENT__MEDIA_FILE_FOO_BAR',
        };

        const wrapper = await createWrapper({
            renameMedia: () =>
                // eslint-disable-next-line prefer-promise-reject-errors
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                error,
                            ],
                        },
                    },
                }),
        });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName(
            'new file name',
            {
                isLoading: false,
            },
            () => {},
        );

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.renamingError.message',
        });
    });

    it('onBlur doesnt update the entity if the value did not change', async () => {
        const wrapper = await createWrapper();
        const item = {
            fileName: 'Test.png',
        };
        const event = { target: { value: item.fileName } };

        wrapper.vm.onChangeName = jest.fn();

        wrapper.vm.onBlur(event, item, () => {});
        expect(wrapper.vm.onChangeName).not.toHaveBeenCalled();
    });

    it('change handler is called if the folder name has changed on blur', async () => {
        const wrapper = await createWrapper();
        const item = {
            fileName: 'Test.png',
        };
        const event = { target: { value: `${item.fileName} Test` } };

        wrapper.vm.onChangeName = jest.fn();

        wrapper.vm.onBlur(event, item, () => {});
        expect(wrapper.vm.onChangeName).toHaveBeenCalled();
    });

    it('onChangeName rejects invalid names', async () => {
        const wrapper = await createWrapper();
        const item = {
            fileName: 'Test.png',
        };

        wrapper.vm.rejectRenaming = jest.fn();

        const emptyName = { target: { value: '' } };
        wrapper.vm.onBlur(emptyName, item, () => {});
        expect(wrapper.vm.rejectRenaming).toHaveBeenCalled();
    });
});
