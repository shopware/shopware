/**
 * @package content
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';

const itemMock = (options = {}) => {
    return {
        getEntityName: () => { return 'media'; },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        avatarUsers: [],
        categories: [],
        productManufacturers: [],
        productMedia: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],
        ...options,
    };
};

async function createWrapper(mediaServiceFunctions = {}) {
    return mount(await wrapTestComponent('sw-media-quickinfo', { sync: true }), {
        props: {
            item: itemMock(),
            editable: true,
        },
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    ...mediaServiceFunctions,
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-alert': true,
                'sw-icon': true,
                'sw-media-collapse': {
                    template: `
                    <div class="sw-media-quickinfo">
                        <slot name="content"></slot>
                    </div>`,
                },
                'sw-media-quickinfo-metadata-item': true,
                'sw-media-preview-v2': true,
                'sw-media-tag': true,
                'sw-custom-field-set-renderer': true,
            },
        },
    });
}

describe('module/sw-media/components/sw-media-quickinfo', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = ['media.deleter'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });

    it.each([
        {
            status: 500,
            code: 'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
        },
        {
            status: 500,
            code: 'CONTENT__MEDIA_EMPTY_FILE',
        },
    ])('should map error %p', async (error) => {
        global.activeAclRoles = ['media.editor'];

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
        await wrapper.vm.$nextTick();

        await wrapper.vm.onChangeFileName('newFileName');

        expect(wrapper.vm.fileNameError).toStrictEqual(error);
    });

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
        await wrapper.vm.onChangeFileName('new file name');

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
        await wrapper.vm.onChangeFileName('new file name');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-media-item.notification.renamingError.message',
        });
    });
});

