/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

const { Module } = Shopware;

// mocking modules
const modulesToCreate = new Map();
modulesToCreate.set('sw-product', {
    icon: 'regular-products',
    color: '#57D9A3',
    entity: 'product',
});
modulesToCreate.set('sw-mail-template', {
    icon: 'regular-cog',
    entity: 'mail_template',
});
modulesToCreate.set('sw-cms', { icon: 'regular-image-text', entity: 'cms_page' });

Array.from(modulesToCreate.keys()).forEach((moduleName) => {
    const currentModuleValues = modulesToCreate.get(moduleName);

    Module.register(moduleName, {
        icon: currentModuleValues.icon,
        color: currentModuleValues.color,
        entity: currentModuleValues.entity,
        routes: {
            index: {
                components: {},
                path: 'index',
            },
        },
    });
});

const ID_MAILTEMPLATE_FOLDER = '4006d6aa64ce409692ac2b952fa56ade';
const ID_PRODUCTS_FOLDER = '0e6b005ca7a1440b8e87ac3d45ed5c9f';
const ID_CONTENT_FOLDER = '08bc82b315c54cb097e5c3fb30f6ff16';

async function createWrapper(defaultFolderId, privileges = []) {
    const repositoryFactoryMock = {
        create: () =>
            Promise.resolve({
                isNew: () => true,
            }),
        search: () =>
            Promise.resolve({
                isNew: () => false,
            }),
        get: (folderId) => {
            switch (folderId) {
                case ID_PRODUCTS_FOLDER:
                    return {
                        entity: 'product',
                        isNew: () => false,
                    };
                case ID_CONTENT_FOLDER:
                    return {
                        entity: 'cms_page',
                        isNew: () => false,
                    };
                case ID_MAILTEMPLATE_FOLDER:
                    return {
                        entity: 'mail_template',
                        isNew: () => false,
                    };
                default:
                    return null;
            }
        },
    };

    return mount(await wrapTestComponent('sw-media-folder-item', { sync: true }), {
        props: {
            item: {
                useParentConfiguration: false,
                configurationId: 'a73ef286f6c748deacdbdfd5aab3cca7',
                defaultFolderId: defaultFolderId,
                parentId: null,
                childCount: 0,
                name: 'Cms Page Media',
                customFields: null,
                createdAt: '2020-06-03T09:44:51+00:00',
                updatedAt: null,
                id: 'af46d5250e34403485e045ba7049dec7',
                children: [],
                isNew: () => false,
                media: [
                    {
                        isNew: () => false,
                    },
                ],
            },
            showSelectionIndicator: false,
            showContextMenuButton: true,
            selected: false,
            isList: true,
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
                    create: () => repositoryFactoryMock,
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
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
                    },
                    // Hack with AllowMultiSelect is needed because the property
                    // can't be accessed in the test utils correctly
                    template: `
                    <div class="sw-media-base-item">
                        AllowMultiSelect: "{{ allowMultiSelect }}"
                        <slot name="preview" v-bind="{ item: $attrs.item }"></slot>
                        <slot name="context-menu" v-bind="{ startInlineEdit: () => {}}"></slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-item': {
                    template: '<div class="sw-context-menu-item"><slot></slot></div>',
                },
                'sw-context-menu': {
                    template: '<div><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-media-folder-thumbnail': {
                    props: [
                        'color',
                        'variant',
                    ],
                    template: '<svg class="sw-media-folder-thumbnail"></svg>',
                },
                'sw-media-modal-folder-settings': true,
                'sw-media-modal-folder-dissolve': true,
                'sw-media-modal-move': true,
                'sw-media-modal-delete': true,
                'sw-time-ago': true,
            },
        },
    });
}

describe('components/media/sw-media-folder-item', () => {
    afterEach(() => {
        useModuleIconColors().enabled.value = false;
    });

    it.each([
        [
            'product module',
            ID_PRODUCTS_FOLDER,
        ],
        [
            'mail template module',
            ID_MAILTEMPLATE_FOLDER,
        ],
        [
            'cms module',
            ID_CONTENT_FOLDER,
        ],
        [
            'fallback',
            undefined,
        ],
    ])('should use the blue folder thumbnail for %s', async (_, defaultFolderId) => {
        const wrapper = await createWrapper(defaultFolderId);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('folder-thumbnail');
    });

    it('should color the module icon with the brand icon token', async () => {
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await flushPromises();

        const innerIcon = wrapper.findComponent('.sw-media-folder-item__folder-thumbnails.is--inner');
        expect(innerIcon.props('name')).toBe('regular-products');
        expect(innerIcon.props('color')).toBe('var(--color-icon-secondary-default)');
        expect(wrapper.findComponent('svg.sw-media-folder-thumbnail').props('color')).toBeUndefined();
    });

    it('should paint the default folder and its icon in the module color when module colors are enabled', async () => {
        useModuleIconColors().enabled.value = true;
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await flushPromises();

        expect(wrapper.findComponent('svg.sw-media-folder-thumbnail').props('color')).toBe('#57D9A3');
        expect(wrapper.findComponent('.sw-media-folder-item__folder-thumbnails.is--inner').props('color')).toBe('#57D9A3');
    });

    it('should keep the folder neutral for a module without a color when module colors are enabled', async () => {
        useModuleIconColors().enabled.value = true;
        const wrapper = await createWrapper(ID_CONTENT_FOLDER);
        await flushPromises();

        expect(wrapper.findComponent('svg.sw-media-folder-thumbnail').props('color')).toBeUndefined();
        expect(wrapper.findComponent('.sw-media-folder-item__folder-thumbnails.is--inner').props('color')).toBe(
            'var(--color-icon-secondary-default)',
        );
    });

    it('should switch the folder color when the user toggles module colors', async () => {
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await flushPromises();

        useModuleIconColors().enabled.value = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent('svg.sw-media-folder-thumbnail').props('color')).toBe('#57D9A3');
    });

    it('should not be able to delete', async () => {
        const aclWrapper = await createWrapper();
        await aclWrapper.vm.$nextTick();

        const deleteMenuItem = aclWrapper.find('.sw-media-context-item__delete-folder-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const aclWrapper = await createWrapper(null, [
            'media.deleter',
        ]);
        await aclWrapper.vm.$nextTick();

        const deleteMenuItem = aclWrapper.find('.sw-media-context-item__delete-folder-action');
        expect(deleteMenuItem.attributes().disabled).toBeDefined();
    });

    it('should not be able to edit', async () => {
        const aclWrapper = await createWrapper();
        await aclWrapper.vm.$nextTick();

        const editMenuItem = aclWrapper.find('.sw-media-context-item__move-folder-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const aclWrapper = await createWrapper(null, [
            'media.editor',
        ]);
        await aclWrapper.vm.$nextTick();

        const editMenuItem = aclWrapper.find('.sw-media-context-item__move-folder-action');
        expect(editMenuItem.attributes().disabled).toBeDefined();
    });

    it('should show the icon when it is not parent', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isParent: false,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('AllowMultiSelect: "true"');
    });

    it('should not show the icon on back folder', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isParent: true,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('AllowMultiSelect: "false"');
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
        if (!Shopware.Feature.isActive('V6_8_0_0')) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
        }
    });

    it('onBlur doesnt update the entity if the value did not change', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;
        const event = { target: { value: item.name } };

        wrapper.vm.onChangeName = jest.fn();

        wrapper.vm.onBlur(event, item, () => {});
        expect(wrapper.vm.onChangeName).not.toHaveBeenCalled();
    });

    it('change handler is called if the folder name has changed on blur', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;
        const event = { target: { value: `${item.name} Test` } };

        wrapper.vm.onChangeName = jest.fn();

        wrapper.vm.onBlur(event, item, () => {});
        expect(wrapper.vm.onChangeName).toHaveBeenCalled();
    });

    it('onChangeName rejects invalid names', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;

        wrapper.vm.rejectRenaming = jest.fn();

        const emptyName = { target: { value: '' } };
        wrapper.vm.onBlur(emptyName, item, () => {});
        expect(wrapper.vm.rejectRenaming).toHaveBeenCalledWith(item, 'empty-name', expect.any(Function));

        const invalidName = { target: { value: 'Test <' } };
        wrapper.vm.onBlur(invalidName, item, () => {});
        expect(wrapper.vm.rejectRenaming).toHaveBeenCalledWith(item, 'invalid-name', expect.any(Function));
    });

    it('should not call the api get default folder if default folder id does not exist', async () => {
        const wrapper = await createWrapper(null);
        await wrapper.vm.$nextTick();

        const mediaDefaultFolderRepositorySpy = jest.spyOn(wrapper.vm.mediaDefaultFolderRepository, 'get');
        await wrapper.vm.getIconConfigFromFolder();

        expect(mediaDefaultFolderRepositorySpy).toHaveBeenCalledTimes(0);
    });

    it('should call the api get default folder if default folder id exists', async () => {
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await wrapper.setData({
            lastDefaultFolderId: '',
        });

        wrapper.vm.mediaDefaultFolderRepository.get = jest.fn(() => Promise.resolve({}));
        wrapper.vm.moduleFactory.getModuleByEntityName = jest.fn(() => Promise.resolve({}));

        await wrapper.vm.getIconConfigFromFolder();
        await flushPromises();

        expect(wrapper.vm.mediaDefaultFolderRepository.get).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.moduleFactory.getModuleByEntityName).toHaveBeenCalledTimes(1);
    });
});
