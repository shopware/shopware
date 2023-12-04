/**
 * @package content
 */
import { mount } from '@vue/test-utils';

const { Module } = Shopware;

// mocking modules
const modulesToCreate = new Map();
modulesToCreate.set('sw-product', { icon: 'regular-products', entity: 'product' });
modulesToCreate.set('sw-mail-template', { icon: 'regular-cog', entity: 'mail_template' });
modulesToCreate.set('sw-cms', { icon: 'regular-content', entity: 'cms_page' });

Array.from(modulesToCreate.keys()).forEach(moduleName => {
    const currentModuleValues = modulesToCreate.get(moduleName);

    Module.register(moduleName, {
        icon: currentModuleValues.icon,
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
                media: [{
                    isNew: () => false,
                }],
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
                    create: () => ({
                        create: () => Promise.resolve({
                            isNew: () => true,
                        }),
                        search: () => Promise.resolve({
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
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

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
            },
        },
    });
}

describe('components/media/sw-media-folder-item', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should provide correct folder color for product module', async () => {
        const wrapper = await createWrapper(ID_PRODUCTS_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail--green');
    });

    it('should provide correct folder color for mail template module', async () => {
        const wrapper = await createWrapper(ID_MAILTEMPLATE_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail--grey');
    });

    it('should provide correct folder color for cms module', async () => {
        const wrapper = await createWrapper(ID_CONTENT_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail--pink');
    });

    it('should provide fallback folder color', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail');
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
        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });
});
