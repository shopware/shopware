import Vue from 'vue';
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid';
import 'src/app/component/form/sw-checkbox-field';
import PrivilegesService from 'src/app/service/privileges.service';

function createWrapper({ privilegesMappings = [], rolePrivileges = [] } = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const privilegesService = new PrivilegesService();
    privilegesMappings.forEach(mapping => {
        privilegesService.addPrivilegeMappingEntry(mapping);
    });

    return shallowMount(Shopware.Component.build('sw-users-permissions-permissions-grid'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-icon': true,
            'sw-field-error': true,
            'sw-base-field': true
        },
        provide: {
            privileges: privilegesService
        },
        mocks: {
            $tc: t => t
        },
        propsData: Vue.observable({
            role: { privileges: rolePrivileges }
        })
    });
}

describe('src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should show the header with all titles', () => {
        const wrapper = createWrapper();

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

    it('should show a row with privileges', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const entry = wrapper.find('[class*=sw-users-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeTruthy();

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .sw-field--checkbox');

        expect(productRow.exists()).toBeTruthy();
        expect(productViewer.exists()).toBeTruthy();
        expect(productEditor.exists()).toBeTruthy();
        expect(productCreator.exists()).toBeTruthy();
        expect(productDeleter.exists()).toBeTruthy();
        expect(productAll.exists()).toBeTruthy();
    });

    it('should show only privileges with the right category', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .sw-field--checkbox');

        expect(productRow.exists()).toBeTruthy();
        expect(productViewer.exists()).toBeTruthy();
        expect(productEditor.exists()).toBeFalsy();
        expect(productCreator.exists()).toBeFalsy();
        expect(productDeleter.exists()).toBeTruthy();
        expect(productAll.exists()).toBeTruthy();
    });

    it('should show only roles which are existing', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');

        expect(productRow.exists()).toBeFalsy();
    });

    it('should ignore role which doesn´t fit in the category', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'additional_permissions',
                    key: 'system',
                    parent: null,
                    roles: {
                        clear_cache: {
                            dependencies: [],
                            privileges: ['system:clear:cache']
                        },
                        core_update: {
                            dependencies: [],
                            privileges: ['system:core:update']
                        },
                        plugin_maintain: {
                            dependencies: [],
                            privileges: ['system:plugin:maintain']
                        }
                    }
                }
            ]
        });

        const entry = wrapper.find('[class*=sw-users-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeFalsy();
    });

    it('should select the viewer role', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges.length).toBe(0);

        productViewer.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges.length).toBe(1);
        expect(wrapper.vm.role.privileges[0]).toBe('product.viewer');
        expect(productViewer.find('.sw-field--checkbox').props().value).toBe(true);
    });

    it('should select the creator role', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        expect(wrapper.vm.role.privileges.length).toBe(0);

        productCreator.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges.length).toBeGreaterThan(0);
        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(productCreator.find('.sw-field--checkbox').props().value).toBe(true);
    });

    it('should select a role and all its dependencies in the same row', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges.length).toBe(0);

        productCreator.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges.length).toBe(3);

        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');

        expect(productViewer.find('.sw-field--checkbox').props().value).toBe(true);
        expect(productEditor.find('.sw-field--checkbox').props().value).toBe(true);
        expect(productCreator.find('.sw-field--checkbox').props().value).toBe(true);
    });

    it('should have enabled checkboxes when selecting a role with its dependencies', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');

        expect(productCreator.props().value).toBe(false);
        expect(productEditor.props().value).toBe(false);
        expect(productViewer.props().value).toBe(false);

        productCreator.find('.sw-field--checkbox input').trigger('click');

        wrapper.vm.$forceUpdate();

        expect(productCreator.props().value).toBe(true);
        expect(productEditor.props().value).toBe(true);
        expect(productViewer.props().value).toBe(true);
    });

    it('should select a role and all its dependencies in other rows', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                },
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_category');
        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const categoryCreator = categoryRow.find('.sw-users-permissions-permissions-grid__role_creator');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');

        expect(wrapper.vm.role.privileges.length).toBe(0);

        categoryCreator.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges.length).toBe(3);

        expect(wrapper.vm.role.privileges).toContain('category.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');

        expect(categoryCreator.find('.sw-field--checkbox').props().value).toBe(true);
        expect(productViewer.find('.sw-field--checkbox').props().value).toBe(true);
        expect(productEditor.find('.sw-field--checkbox').props().value).toBe(true);
    });

    it('should select a role and add it to the role privileges prop', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        productCreator.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges).toContain('product.creator');
    });

    it('should select a role and all dependencies to the role privileges prop', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator');

        productCreator.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');
    });

    it('should select all and all roles in the row should be selected', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        expect(wrapper.vm.role.privileges).not.toContain('product.viewer');
        expect(wrapper.vm.role.privileges).not.toContain('product.editor');
        expect(wrapper.vm.role.privileges).not.toContain('product.creator');
        expect(wrapper.vm.role.privileges).not.toContain('product.deleter');

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        productAll.find('.sw-field--checkbox input').trigger('click');

        expect(wrapper.vm.role.privileges).toContain('product.viewer');
        expect(wrapper.vm.role.privileges).toContain('product.editor');
        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.deleter');
    });

    it('should select all and all checkboxes in the row should be selected', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');
        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        expect(productViewer.props().value).toBe(false);
        expect(productEditor.props().value).toBe(false);
        expect(productCreator.props().value).toBe(false);
        expect(productDeleter.props().value).toBe(false);

        productAll.find('.sw-field--checkbox input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(true);
        expect(productEditor.props().value).toBe(true);
        expect(productCreator.props().value).toBe(true);
        expect(productDeleter.props().value).toBe(true);
    });
    it('should select all and roles in other rows should not be selected', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                },
                {
                    category: 'permissions',
                    key: 'category',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        const categoryRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_category');
        const categoryViewer = categoryRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const categoryEditor = categoryRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const categoryCreator = categoryRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const categoryDeleter = categoryRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        expect(productViewer.props().value).toBe(false);
        expect(productEditor.props().value).toBe(false);
        expect(productCreator.props().value).toBe(false);
        expect(productDeleter.props().value).toBe(false);

        expect(categoryViewer.props().value).toBe(false);
        expect(categoryEditor.props().value).toBe(false);
        expect(categoryCreator.props().value).toBe(false);
        expect(categoryDeleter.props().value).toBe(false);

        productAll.find('.sw-field--checkbox input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(true);
        expect(productEditor.props().value).toBe(true);
        expect(productCreator.props().value).toBe(true);
        expect(productDeleter.props().value).toBe(true);

        expect(categoryViewer.props().value).toBe(false);
        expect(categoryEditor.props().value).toBe(false);
        expect(categoryCreator.props().value).toBe(false);
        expect(categoryDeleter.props().value).toBe(false);
    });

    it('should select some and click on all. All have to be selected', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all');

        productViewer.find('.sw-field--checkbox input').trigger('click');
        productCreator.find('.sw-field--checkbox input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(true);
        expect(productEditor.props().value).toBe(false);
        expect(productCreator.props().value).toBe(true);
        expect(productDeleter.props().value).toBe(false);

        productAll.find('.sw-field--checkbox input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(true);
        expect(productEditor.props().value).toBe(true);
        expect(productCreator.props().value).toBe(true);
        expect(productDeleter.props().value).toBe(true);
    });
    it('should select all roles each and the checkbox all have to be checked', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .sw-field--checkbox');

        expect(productAll.props().value).toBe(false);

        productViewer.find('.sw-field--checkbox input').trigger('click');
        productEditor.find('.sw-field--checkbox input').trigger('click');
        productCreator.find('.sw-field--checkbox input').trigger('click');
        productDeleter.find('.sw-field--checkbox input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productAll.props().value).toBe(true);
    });

    it('should unselect all roles with the checkbox all', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [],
                            privileges: []
                        },
                        creator: {
                            dependencies: [],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        const productAll = productRow.find('.sw-users-permissions-permissions-grid__all .sw-field--checkbox');

        productViewer.find('input').trigger('click');
        productEditor.find('input').trigger('click');
        productCreator.find('input').trigger('click');
        productDeleter.find('input').trigger('click');

        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(true);
        expect(productEditor.props().value).toBe(true);
        expect(productCreator.props().value).toBe(true);
        expect(productDeleter.props().value).toBe(true);

        productAll.find('input').trigger('click');
        wrapper.vm.$forceUpdate();

        expect(productViewer.props().value).toBe(false);
        expect(productEditor.props().value).toBe(false);
        expect(productCreator.props().value).toBe(false);
        expect(productDeleter.props().value).toBe(false);
    });

    it('should disable checkboxes which are dependencies for viewer (0 dependencies)', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        productViewer.find('input').trigger('click');

        expect(productViewer.props().value).toBe(true);
        expect(productViewer.props().disabled).toBe(false);

        expect(productEditor.props().value).toBe(false);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().value).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().value).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for editor (1 dependency)', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        productEditor.find('input').trigger('click');

        expect(productViewer.props().value).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().value).toBe(true);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().value).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().value).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for creator (2 dependencies)', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        productCreator.find('input').trigger('click');

        expect(productViewer.props().value).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().value).toBe(true);
        expect(productEditor.props().disabled).toBe(true);

        expect(productCreator.props().value).toBe(true);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().value).toBe(false);
        expect(productDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for deleter (1 dependency)', () => {
        const wrapper = createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: []
                        },
                        editor: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        },
                        creator: {
                            dependencies: [
                                'product.viewer',
                                'product.editor'
                            ],
                            privileges: []
                        },
                        deleter: {
                            dependencies: [
                                'product.viewer'
                            ],
                            privileges: []
                        }
                    }
                }
            ]
        });

        const productRow = wrapper.find('.sw-users-permissions-permissions-grid__entry_product');
        const productViewer = productRow.find('.sw-users-permissions-permissions-grid__role_viewer .sw-field--checkbox');
        const productEditor = productRow.find('.sw-users-permissions-permissions-grid__role_editor .sw-field--checkbox');
        const productCreator = productRow.find('.sw-users-permissions-permissions-grid__role_creator .sw-field--checkbox');
        const productDeleter = productRow.find('.sw-users-permissions-permissions-grid__role_deleter .sw-field--checkbox');

        productDeleter.find('input').trigger('click');

        expect(productViewer.props().value).toBe(true);
        expect(productViewer.props().disabled).toBe(true);

        expect(productEditor.props().value).toBe(false);
        expect(productEditor.props().disabled).toBe(false);

        expect(productCreator.props().value).toBe(false);
        expect(productCreator.props().disabled).toBe(false);

        expect(productDeleter.props().value).toBe(true);
        expect(productDeleter.props().disabled).toBe(false);
    });
});
