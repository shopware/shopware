/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(mediaServiceFunctions = {}, props = {}) {
    return mount(await wrapTestComponent('sw-media-media-item', { sync: true }), {
        global: {
            provide: {
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    ...mediaServiceFunctions,
                },
            },
            stubs: {
                'sw-media-base-item': {
                    props: {
                        allowMultiSelect: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                        item: {
                            type: Object,
                            required: true,
                        },
                        allowEdit: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                        allowDelete: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                    },
                    template: `
                    <div class="sw-media-base-item">
                        <slot name="preview" v-bind="{ item }"></slot>
                        <slot name="context-menu" v-bind="{ item, allowEdit, allowDelete }"></slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-media-preview-v2': true,
                'sw-text-field': true,
                'sw-context-menu-item': true,
                'sw-media-modal-replace': true,
                'sw-media-modal-delete': true,
                'sw-media-modal-move': true,
                'sw-app-action-button': true,
            },
        },
        props: {
            item: {
                url: 'https://example.com/Test.png',
                fileName: 'Test.png',
                fileSize: 12345,
                mimeType: 'image/png',
                id: 'media-id',
                hasFile: true,
                private: false,
                ...props.item,
            },
            ...props,
        },
    });
}

describe('components/media/sw-media-media-item', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
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
            hasFile: true,
            private: false,
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
            hasFile: true,
            private: false,
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
            hasFile: true,
            private: false,
        };

        wrapper.vm.rejectRenaming = jest.fn();

        const emptyName = { target: { value: '' } };
        wrapper.vm.onBlur(emptyName, item, () => {});
        expect(wrapper.vm.rejectRenaming).toHaveBeenCalled();
    });

    it('should show action button from apps', async () => {
        Shopware.Store.get('actionButtons').add({
            name: 'media-button',
            entity: 'media',
            view: 'item',
            label: 'Navigate to app',
        });

        const wrapper = await createWrapper();
        const actionButton = wrapper.find('sw-app-action-button-stub');
        expect(actionButton.exists()).toBeTruthy();
    });

    it('should call the action button method', async () => {
        const actionButtonMethod = jest.fn();
        const action = {
            name: 'media-button',
            entity: 'media',
            view: 'item',
            label: 'Navigate to app',
            callback: actionButtonMethod,
        };

        Shopware.Store.get('actionButtons').add(action);

        const wrapper = await createWrapper();
        const actionButton = wrapper.findComponent('sw-app-action-button-stub');

        await actionButton.vm.$emit('run-app-action', action);

        expect(actionButtonMethod).toHaveBeenCalledWith({
            fileName: 'Test.png',
            id: 'media-id',
            url: 'https://example.com/Test.png',
            mimeType: 'image/png',
            fileSize: 12345,
        });
    });
});
