/**
 * @sw-package fundamentals@framework
 */
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';

async function createWrapper({ privilegesMappings = [], rolePrivileges = [] } = {}) {
    const privilegesService = new PrivilegesService();
    privilegesMappings.forEach((mapping) => {
        privilegesService.addPrivilegeMappingEntry(mapping);
    });

    const wrapper = mount(
        await wrapTestComponent('sw-users-permissions-permissions-grid', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', {
                        sync: true,
                    }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', {
                        sync: true,
                    }),
                    'sw-field-error': true,
                    'sw-base-field': true,
                },
                provide: {
                    privileges: privilegesService,
                },
            },
            props: reactive({
                role: { privileges: rolePrivileges },
            }),
        },
    );

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the header with all titles', async () => {
        const wrapper = await createWrapper();

        const gridHeader = wrapper.find('.sw-users-permissions-permissions-grid__entry-header');
        const gridTitle = gridHeader.find('.sw-users-permissions-permissions-grid__title');
        const gridRoleViewer = gridHeader.findAll('.sw-users-permissions-permissions-grid__checkbox-wrapper').at(0);
        const gridRoleEditor = gridHeader.findAll('.sw-users-permissions-permissions-grid__checkbox-wrapper').at(1);
        const gridRoleCreator = gridHeader.findAll('.sw-users-permissions-permissions-grid__checkbox-wrapper').at(2);
        const gridRoleDeleter = gridHeader.findAll('.sw-users-permissions-permissions-grid__checkbox-wrapper').at(3);
        const gridRoleAll = gridHeader.find('.sw-users-permissions-permissions-grid__all');

        expect(gridHeader.exists()).toBeTruthy();

        expect(gridTitle.exists()).toBeTruthy();
        expect(gridTitle.text()).toBe('');

        expect(gridRoleViewer.exists()).toBeTruthy();
        expect(gridRoleViewer.text()).toBe('sw-privileges.roles.viewer');

        expect(gridRoleEditor.exists()).toBeTruthy();
        expect(gridRoleEditor.text()).toBe('sw-privileges.roles.editor');

        expect(gridRoleCreator.exists()).toBeTruthy();
        expect(gridRoleCreator.text()).toBe('sw-privileges.roles.creator');

        expect(gridRoleDeleter.exists()).toBeTruthy();
        expect(gridRoleDeleter.text()).toBe('sw-privileges.roles.deleter');

        expect(gridRoleAll.exists()).toBeTruthy();
        expect(gridRoleAll.text()).toBe('sw-privileges.roles.all');
    });

    it('should show a row with privileges', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const entry = wrapper.find('div[class*=sw-users-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeTruthy();

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .mt-field--checkbox__container');

        expect(productRow.exists()).toBeTruthy();
        expect(productViewer.exists()).toBeTruthy();
        expect(productEditor.exists()).toBeTruthy();
        expect(productCreator.exists()).toBeTruthy();
        expect(productDeleter.exists()).toBeTruthy();
        expect(productAll.exists()).toBeTruthy();
    });

    it('should show only privileges with the right category', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .mt-field--checkbox__container');

        expect(productRow.exists()).toBeTruthy();
        expect(productViewer.exists()).toBeTruthy();
        expect(productEditor.exists()).toBeFalsy();
        expect(productCreator.exists()).toBeFalsy();
        expect(productDeleter.exists()).toBeTruthy();
        expect(productAll.exists()).toBeTruthy();
    });

    it('should show only roles which are existing', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        expect(productRow.exists()).toBeFalsy();
    });

    it('should ignore role which doesn´t fit in the category', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'additional_permissions',
                    key: 'system',
                    parent: null,
                    roles: {
                        clear_cache: {
                            dependencies: [],
                            privileges: ['system:clear:cache'],
                        },
                        core_update: {
                            dependencies: [],
                            privileges: ['system:core:update'],
                        },
                        plugin_maintain: {
                            dependencies: [],
                            privileges: ['system:plugin:maintain'],
                        },
                    },
                },
            ],
        });

        const entry = wrapper.find('div[class*=sw-users-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeFalsy();
    });

    it('should select the viewer role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await productViewer.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(1);
        expect(wrapper.vm.role.privileges[0]).toBe('product.viewer');
        expect(productViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should have selected the viewer role directly', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: ['product.viewer'],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor');

        expect(productViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(productEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(false);
    });

    it('should select the creator role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await productCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges.length).toBeGreaterThan(0);
        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(productCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should select a role and all its dependencies in the same row', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await productCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(3);

        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');

        expect(productViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(productEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(productCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should have enabled checkboxes when selecting a role with its dependencies', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        expect(productCreator.props().checked).toBe(false);
        expect(productEditor.props().checked).toBe(false);
        expect(productViewer.props().checked).toBe(false);

        await productCreator.find('.mt-field--checkbox__container input').setChecked();

        wrapper.vm.$forceUpdate();

        expect(productCreator.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(true);
        expect(productViewer.props().checked).toBe(true);
    });

    it('should select a role and all its dependencies in other rows', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_category');
        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryCreator = categoryRow.find('.sw-users-permissions-permissions-grid__role_creator');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await categoryCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(3);

        expect(wrapper.vm.role.privileges).toContain('category.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');

        expect(categoryCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(productViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(productEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should select a role and add it to the role privileges prop', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        await productCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('product.creator');
    });

    it('should select a role and all dependencies to the role privileges prop', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        await productCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');
    });

    it('should select all and all roles in the row should be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        expect(wrapper.vm.role.privileges).not.toContain('product.viewer');
        expect(wrapper.vm.role.privileges).not.toContain('product.editor');
        expect(wrapper.vm.role.privileges).not.toContain('product.creator');
        expect(wrapper.vm.role.privileges).not.toContain('product.deleter');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        await productAll.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('product.viewer');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.deleter');
    });

    it('should select all and all checkboxes in the row should be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        expect(productViewer.props().checked).toBe(false);
        expect(productEditor.props().checked).toBe(false);
        expect(productCreator.props().checked).toBe(false);
        expect(productDeleter.props().checked).toBe(false);

        await productAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(true);
        expect(productCreator.props().checked).toBe(true);
        expect(productDeleter.props().checked).toBe(true);
    });
    it('should select all and roles in other rows should not be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_category');
        const categoryViewer = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditor = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryCreator = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const categoryDeleter = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        expect(productViewer.props().checked).toBe(false);
        expect(productEditor.props().checked).toBe(false);
        expect(productCreator.props().checked).toBe(false);
        expect(productDeleter.props().checked).toBe(false);

        expect(categoryViewer.props().checked).toBe(false);
        expect(categoryEditor.props().checked).toBe(false);
        expect(categoryCreator.props().checked).toBe(false);
        expect(categoryDeleter.props().checked).toBe(false);

        await productAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(true);
        expect(productCreator.props().checked).toBe(true);
        expect(productDeleter.props().checked).toBe(true);

        expect(categoryViewer.props().checked).toBe(false);
        expect(categoryEditor.props().checked).toBe(false);
        expect(categoryCreator.props().checked).toBe(false);
        expect(categoryDeleter.props().checked).toBe(false);
    });

    it('should select some and click on all. All have to be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        await productViewer.find('.mt-field--checkbox__container input').setChecked();
        await productCreator.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(false);
        expect(productCreator.props().checked).toBe(true);
        expect(productDeleter.props().checked).toBe(false);

        await productAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(true);
        expect(productCreator.props().checked).toBe(true);
        expect(productDeleter.props().checked).toBe(true);
    });
    it('should select all roles each and the checkbox all have to be checked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const productAll = productRow
            .find('.sw-users-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        expect(productAll.props().checked).toBe(false);

        await productViewer.find('.mt-field--checkbox__container input').setChecked();
        await productEditor.find('.mt-field--checkbox__container input').setChecked();
        await productCreator.find('.mt-field--checkbox__container input').setChecked();
        await productDeleter.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(productAll.props().checked).toBe(true);
    });

    it('should select all roles each and the checkbox all have to be checked (privilege has only two roles)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const productAll = productRow
            .find('.sw-users-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // prove that creator and deleter checkbox do not exist
        expect(productCreator.exists()).toBe(false);
        expect(productDeleter.exists()).toBe(false);

        // verify that all checkboxes are not checked
        expect(productViewer.props('checked')).toBe(false);
        expect(productEditor.props('checked')).toBe(false);
        expect(productAll.props('checked')).toBe(false);

        // check viewer and editor role
        await productViewer.find('input[type="checkbox"]').setChecked();
        await productEditor.find('input[type="checkbox"]').setChecked();

        // check state of viewer, editor and all checkbox
        expect(productViewer.props('checked')).toBe(true);
        expect(productEditor.props('checked')).toBe(true);
        expect(productAll.props('checked')).toBe(true);
    });

    it('should unselect all roles with the checkbox all', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .mt-field--checkbox__container');

        await productViewer.find('input').setChecked();
        await productEditor.find('input').setChecked();
        await productCreator.find('input').setChecked();
        await productDeleter.find('input').setChecked();

        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(true);
        expect(productEditor.props().checked).toBe(true);
        expect(productCreator.props().checked).toBe(true);
        expect(productDeleter.props().checked).toBe(true);

        await productAll.find('input').setChecked(false);
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().checked).toBe(false);
        expect(productEditor.props().checked).toBe(false);
        expect(productCreator.props().checked).toBe(false);
        expect(productDeleter.props().checked).toBe(false);
    });

    it('should disable checkboxes which are dependencies for viewer (0 dependencies)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await productViewer.find('input').setChecked();

        expect(productViewer.props().checked).toBe(true);
        expect(productViewer.props().disabled).toBe(false);

        expect(productEditor.props().checked).toBe(false);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().checked).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().checked).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for editor (1 dependency)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await productEditor.find('input').setChecked();

        expect(productViewer.props().checked).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().checked).toBe(true);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().checked).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().checked).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for creator (2 dependencies)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await productCreator.find('input').setChecked();

        expect(productViewer.props().checked).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().checked).toBe(true);
        expect(productEditor.props().disabled).toBe(true);

        expect(productCreator.props().checked).toBe(true);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().checked).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for deleter (1 dependency)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await productDeleter.find('input').setChecked();

        expect(productViewer.props().checked).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().checked).toBe(false);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().checked).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().checked).toBe(true);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should show the parent permissions', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['product.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['product.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const parentGrids = wrapper.findAll('.sw-users-permissions-permissions-grid__parent');
        expect(parentGrids).toHaveLength(3);

        const parentCatalogues = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const parentNull = wrapper.find('.sw-users-permissions-permissions-grid__parent_null');
        const parentSettings = wrapper.find('.sw-users-permissions-permissions-grid__parent_settings');

        expect(parentCatalogues.isVisible()).toBeTruthy();
        expect(parentNull.isVisible()).toBeTruthy();
        expect(parentSettings.isVisible()).toBeTruthy();
    });

    it('should organize the children to the matching parents', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['product.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['product.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const gridEntries = wrapper.findAll('.sw-users-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(8);

        // header in beginning
        expect(gridEntries.at(0).classes()).toContain('sw-users-permissions-permissions-grid__entry-header');

        // catalogues with children
        expect(gridEntries.at(1).classes()).toContain('sw-users-permissions-permissions-grid__parent_catalogues');
        expect(gridEntries.at(2).classes()).toContain('sw-users-permissions-permissions-grid__entry_categories');
        expect(gridEntries.at(3).classes()).toContain('sw-users-permissions-permissions-grid__entry_product');

        // other (null) with children
        expect(gridEntries.at(4).classes()).toContain('sw-users-permissions-permissions-grid__parent_null');
        expect(gridEntries.at(5).classes()).toContain('sw-users-permissions-permissions-grid__entry_sales_channel');

        // settings with children
        expect(gridEntries.at(6).classes()).toContain('sw-users-permissions-permissions-grid__parent_settings');
        expect(gridEntries.at(7).classes()).toContain('sw-users-permissions-permissions-grid__entry_currencies');
    });

    it('should sort parents alphabetically with the label', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'orders',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: 'content',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {},
                },
            ],
        });

        const gridEntries = wrapper.findAll('.sw-users-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(9);

        // check if order is sorted alphabetically
        expect(gridEntries.at(1).classes()).toContain('sw-users-permissions-permissions-grid__parent_catalogues');
        expect(gridEntries.at(3).classes()).toContain('sw-users-permissions-permissions-grid__parent_content');
        expect(gridEntries.at(5).classes()).toContain('sw-users-permissions-permissions-grid__parent_orders');
        expect(gridEntries.at(7).classes()).toContain('sw-users-permissions-permissions-grid__parent_settings');
    });

    it('should sort children in parents alphabetically', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'rule_builder',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'basic_information',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'documents',
                    parent: 'settings',
                    roles: {},
                },
            ],
        });

        const gridEntries = wrapper.findAll('.sw-users-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(6);

        // check if order is sorted alphabetically
        expect(gridEntries.at(2).classes()).toContain('sw-users-permissions-permissions-grid__entry_basic_information');
        expect(gridEntries.at(3).classes()).toContain('sw-users-permissions-permissions-grid__entry_currencies');
        expect(gridEntries.at(4).classes()).toContain('sw-users-permissions-permissions-grid__entry_documents');
        expect(gridEntries.at(5).classes()).toContain('sw-users-permissions-permissions-grid__entry_rule_builder');
    });

    it('parent checkbox should be partial checked when some of the child permission is clicked (TODO)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueViewerCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueViewerCheckbox.props().partial).toBe(false);
        expect(catalogueEditorCheckbox.props().partial).toBe(false);

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productEditorCheckbox = productRow.find(
            '.sw-users-permissions-permissions-grid__role_editor .mt-field--checkbox__container',
        );

        await productEditorCheckbox.find('input').setChecked();

        expect(catalogueViewerCheckbox.props().partial).toBe(true);
        expect(catalogueEditorCheckbox.props().partial).toBe(true);
    });

    it('parent checkbox should be partial checked when some of the child permission is clicked (all)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueAllCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_all')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueAllCheckbox.props().partial).toBe(false);

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productAllCheckbox = productRow.find(
            '.sw-users-permissions-permissions-grid__role_all .mt-field--checkbox__container',
        );

        await productAllCheckbox.find('input').setChecked();

        expect(catalogueAllCheckbox.props().partial).toBe(true);
    });

    it('parent checkbox should be checked when all of the child permission is clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueEditorCheckbox.props().checked).toBe(false);
        expect(productEditorCheckbox.props().checked).toBe(false);
        expect(categoryEditorCheckbox.props().checked).toBe(false);

        await productEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(false);

        await categoryEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should be disabled when all of the child permission are disabled', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueViewerCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueViewerCheckbox.props().disabled).toBe(false);

        await productEditorCheckbox.find('input').setChecked();

        expect(catalogueViewerCheckbox.props().disabled).toBe(false);

        await categoryEditorCheckbox.find('input').setChecked();

        expect(catalogueViewerCheckbox.props().disabled).toBe(true);
    });

    it('parent checkbox should check all of the child permission when clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueEditorCheckbox.props().checked).toBe(false);
        expect(productEditorCheckbox.props().checked).toBe(false);
        expect(categoryEditorCheckbox.props().checked).toBe(false);

        await catalogueEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should check the child permission except missing roles when clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        // Missing editor role for categories
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(catalogueEditorCheckbox.props().checked).toBe(false);
        expect(productEditorCheckbox.props().checked).toBe(false);
        expect(categoryEditorCheckbox.exists()).toBeFalsy();

        await catalogueEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.exists()).toBeFalsy();

        expect(wrapper.vm.role.privileges).not.toContain('categories.editor');
    });

    // eslint-disable-next-line max-len
    it('parent checkbox should check all of the child permission when clicked and some child permissions are already clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        await productEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(false);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.props().checked).toBe(false);

        await catalogueEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should uncheck all of the child permission when unchecked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueEditorCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        await productEditorCheckbox.find('input').setChecked();
        await categoryEditorCheckbox.find('input').setChecked();

        expect(catalogueEditorCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryEditorCheckbox.props().checked).toBe(true);

        await catalogueEditorCheckbox.find('input').setChecked(false);

        expect(catalogueEditorCheckbox.props().checked).toBe(false);
        expect(productEditorCheckbox.props().checked).toBe(false);
        expect(categoryEditorCheckbox.props().checked).toBe(false);
    });

    it('parent checkbox should uncheck all of the child permission when unchecked expect disabled checkboxes', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const cataloguesRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogues');
        const catalogueViewerCheckbox = cataloguesRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_categories');
        const productViewerCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditorCheckbox = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryEditorCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const categoryViewerCheckbox = categoryRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // check product.editor
        await productEditorCheckbox.find('input').setChecked();

        expect(catalogueViewerCheckbox.props().checked).toBe(false);
        expect(productViewerCheckbox.props().checked).toBe(true);
        expect(productEditorCheckbox.props().checked).toBe(true);
        expect(categoryViewerCheckbox.props().checked).toBe(false);
        expect(categoryEditorCheckbox.props().checked).toBe(false);

        // check all catalogues viewer children
        await categoryViewerCheckbox.find('input').setChecked();

        expect(catalogueViewerCheckbox.props().checked).toBe(true);
        expect(productViewerCheckbox.props().checked).toBe(true);
        expect(categoryViewerCheckbox.props().checked).toBe(true);

        // uncheck all catalogues viewer children
        await catalogueViewerCheckbox.find('input').setChecked(false);

        expect(productViewerCheckbox.props().checked).toBe(true);
        expect(categoryViewerCheckbox.props().checked).toBe(false);
    });

    it('should disable all checkboxes', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'categories',
                    parent: 'catalogues',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'categories.viewer',
                                'categories.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['categories.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'currencies',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'currencies.viewer',
                                'currencies.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['currencies.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'sales_channel',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'sales_channel.viewer',
                                'sales_channel.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['sales_channel.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const checkboxes = wrapper.findAllComponents({
            name: 'sw-checkbox-field-deprecated__wrapped',
        });

        checkboxes.forEach((checkbox) => {
            expect(checkbox.props().disabled).toBe(false);
        });

        await wrapper.setProps({ disabled: true });

        checkboxes.forEach((checkbox) => {
            expect(checkbox.props().disabled).toBe(true);
        });
    });

    it('should not exist in the DOM if it has no child roles', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogue',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        // get product viewer checkbox
        const catalogueRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogue');
        const catalogueViewer = catalogueRow.find(
            '.sw-users-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // get product viewer checkbox
        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // assert that catalogue parent does not exist
        expect(catalogueViewer.exists()).toBe(false);

        // assert that child does not exist
        expect(productViewer.exists()).toBe(false);
    });

    it('should exist in the DOM if it has at least one child role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogue',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'property',
                    parent: 'catalogue',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        // get product viewer checkbox
        const catalogueRow = wrapper.find('.sw-users-permissions-permissions-grid__parent_catalogue');
        const catalogueViewer = catalogueRow.find(
            '.sw-users-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // get product viewer checkbox
        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // get property viewer checkbox
        const propertyRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_property');
        const propertyViewer = propertyRow.find(
            '.sw-users-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // assert that catalogue parent does exist
        expect(catalogueViewer.exists()).toBe(true);

        // assert that product child does not exist
        expect(productViewer.exists()).toBe(false);

        // assert that property child does exist
        expect(propertyViewer.exists()).toBe(true);
    });

    /*
     * Write test that checks the select all roles button and prove that the header role that has one missing role
     * has no partial value and is not checked. but all the others are.
     *
     * also check that the select all header checkbox has a partial value
     */
    it('should only add permissions that exist using the check all box', async () => {
        /** @type Wrapper */
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: 'catalogue',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'property',
                    parent: 'catalogue',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const productEditor = productRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const productCreator = productRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const productDeleter = productRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const productAll = productRow
            .find('.sw-users-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // check that product viewer box does not exist
        expect(productViewer.exists()).toBe(false);

        // assert that every checkbox has a state of false
        expect(productEditor.props('checked')).toBe(false);
        expect(productCreator.props('checked')).toBe(false);
        expect(productDeleter.props('checked')).toBe(false);
        expect(productAll.props('checked')).toBe(false);

        await productAll.find('input[type="checkbox"]').setChecked();

        expect(productEditor.props('checked')).toBe(true);
        expect(productCreator.props('checked')).toBe(true);
        expect(productDeleter.props('checked')).toBe(true);
        expect(productAll.props('checked')).toBe(true);

        const headerRow = wrapper.find('.sw-users-permissions-permissions-grid__parent');
        const headerViewer = headerRow
            .find('.sw-users-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const headerEditor = headerRow
            .find('.sw-users-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const headerCreator = headerRow
            .find('.sw-users-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const headerDeleter = headerRow
            .find('.sw-users-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const headerAll = headerRow
            .find('.sw-users-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // assert that viewer header checkbox as not value and no partial value
        expect(headerViewer.props('partial')).toBe(false);
        expect(headerViewer.props('checked')).toBe(false);

        // check that every other header checkbox has a partial value of true and a value of false
        expect(headerEditor.props('partial')).toBe(true);
        expect(headerEditor.props('checked')).toBe(false);

        expect(headerCreator.props('partial')).toBe(true);
        expect(headerCreator.props('checked')).toBe(false);

        expect(headerDeleter.props('partial')).toBe(true);
        expect(headerDeleter.props('checked')).toBe(false);

        expect(headerAll.props('partial')).toBe(true);
        expect(headerAll.props('checked')).toBe(false);
    });
});
