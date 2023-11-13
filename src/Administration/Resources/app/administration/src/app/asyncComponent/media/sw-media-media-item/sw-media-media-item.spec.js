/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import swMediaMediaItem from 'src/app/asyncComponent/media/sw-media-media-item';

Shopware.Component.register('sw-media-media-item', swMediaMediaItem);

async function createWrapper(mediaServiceFunctions = {}) {
    return mount(await Shopware.Component.build('sw-media-media-item'), {
        provide: {
            mediaService: {
                renameMedia: () => Promise.resolve(),
                ...mediaServiceFunctions,
            },
        },
        stubs: {
            'sw-media-base-item': true,
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

        const wrapper = await createWrapper(
            {
                // eslint-disable-next-line prefer-promise-reject-errors
                renameMedia: () => Promise.reject(
                    {
                        response: {
                            data: {
                                errors: [
                                    error,
                                ],
                            },
                        },
                    },
                ),
            },
        );

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName('new file name', {
            isLoading: false,
        }, () => {});

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

        const wrapper = await createWrapper(
            {
                // eslint-disable-next-line prefer-promise-reject-errors
                renameMedia: () => Promise.reject(
                    {
                        response: {
                            data: {
                                errors: [
                                    error,
                                ],
                            },
                        },
                    },
                ),
            },
        );

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName('new file name', {
            isLoading: false,
        }, () => {});

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.renamingError.message',
        });
    });
});
