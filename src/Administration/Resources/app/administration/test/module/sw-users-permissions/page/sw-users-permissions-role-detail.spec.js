import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions-role-detail';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-button';
import PrivilegesService from 'src/app/service/privileges.service';

let privilegesService = new PrivilegesService();

function isNew() {
    return false;
}

function createWrapper({
    privileges = [],
    privilegeMappingEntries = [],
    aclPrivileges = []
} = {}, options = {
    isNew: false
}) {
    privilegeMappingEntries.forEach(mappingEntry => privilegesService.addPrivilegeMappingEntry(mappingEntry));

    const $route = options.isNew ? { params: {} } : { params: { id: '12345789' } };

    return shallowMount(Shopware.Component.build('sw-users-permissions-role-detail'), {
        sync: false,
        stubs: {
            'sw-page': { template: `
<div>
    <slot name="smart-bar-header"></slot>
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>
    ` },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-icon': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-field': true,
            'sw-users-permissions-permissions-grid': true,
            'sw-users-permissions-additional-permissions': true,
            'sw-verify-user-modal': true,
            'sw-tabs': true,
            'router-view': true
        },
        mocks: {
            $route: $route
        },
        propsData: {},
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return aclPrivileges.includes(identifier);
                }
            },
            loginService: {},
            repositoryFactory: {
                create: () => ({
                    create: () => ({
                        isNew: () => true,
                        name: ''
                    }),
                    get: () => Promise.resolve({
                        isNew: isNew,
                        name: 'demoRole',
                        privileges: privileges
                    }),
                    save: jest.fn(() => Promise.resolve())
                })
            },
            userService: {},
            privileges: privilegesService
        }
    });
}

describe('module/sw-users-permissions/page/sw-users-permissions-role-detail', () => {
    beforeEach(() => {
        privilegesService = new PrivilegesService();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not contain any privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['system:clear:cache', 'system.clear_cache']
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.role.privileges.length).toBe(0);
    });

    it('should contain only role privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['system:clear:cache', 'system.clear_cache'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
    });

    it('should contain only roles privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['orders.create_discounts', 'system.clear_cache'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'orders',
                    roles: {
                        create_discounts: {
                            privileges: ['order:create:discount'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).toContain('orders.create_discounts');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
        expect(wrapper.vm.role.privileges).not.toContain('order:create:discount');
    });

    it('should filter custom privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['orders.create_discounts', 'system.clear_cache', 'product:update', 'order:read'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'orders',
                    roles: {
                        create_discounts: {
                            privileges: ['order:create:discount'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).toContain('orders.create_discounts');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
        expect(wrapper.vm.role.privileges).not.toContain('order:create:discount');
        expect(wrapper.vm.role.privileges).not.toContain('product:update');
        expect(wrapper.vm.role.privileges).not.toContain('order:read');

        expect(wrapper.vm.detailedPrivileges).toEqual([
            'product:update',
            'order:read'
        ]);
    });

    it('should save privilege with all privileges and admin privilege key combination', async () => {
        const wrapper = createWrapper({
            privileges: ['system.clear_cache'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    ...wrapper.vm.privileges.getRequiredPrivileges()
                ].sort()
            },
            contextMock
        );
    });

    it('should save privileges with all privileges and admin privilege key combinations', async () => {
        const wrapper = createWrapper({
            privileges: ['system.clear_cache', 'orders.create_discounts'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'orders',
                    roles: {
                        create_discounts: {
                            privileges: ['order:create:discount'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'orders.create_discounts',
                    'order:create:discount',
                    ...wrapper.vm.privileges.getRequiredPrivileges()
                ].sort()
            },
            contextMock
        );
    });

    it('should save privileges with all privileges, admin privilege key combinations and detailed privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['system.clear_cache', 'orders.create_discounts', 'product:read'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'orders',
                    roles: {
                        create_discounts: {
                            privileges: ['order:create:discount'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'orders.create_discounts',
                    'order:create:discount',
                    ...wrapper.vm.privileges.getRequiredPrivileges(),
                    'product:read'
                ].sort()
            },
            contextMock
        );
    });

    it('should merge privileges and detailed privileges', async () => {
        const wrapper = createWrapper({
            privileges: ['system.clear_cache', 'orders.create_discounts', 'product:read'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: []
                        }
                    }
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'orders',
                    roles: {
                        create_discounts: {
                            privileges: ['order:create:discount'],
                            dependencies: []
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        wrapper.vm.detailedPrivileges.push('currency:update');

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'orders.create_discounts',
                    'order:create:discount',
                    ...wrapper.vm.privileges.getRequiredPrivileges(),
                    'product:read',
                    'currency:update'
                ].sort()
            },
            contextMock
        );
    });

    it('should save privileges with all privileges from getPrivileges() method', async () => {
        const wrapper = createWrapper({
            privileges: ['promotion.viewer', 'promotion.editor', 'promotion.creator'],
            privilegeMappingEntries: [
                {
                    category: 'permissions',
                    parent: null,
                    key: 'rule',
                    roles: {
                        viewer: {
                            privileges: ['rule:read'],
                            dependencies: []
                        },
                        editor: {
                            privileges: ['rule:update'],
                            dependencies: [
                                'rule.viewer'
                            ]
                        },
                        creator: {
                            privileges: ['rule:create'],
                            dependencies: [
                                'rule.viewer',
                                'rule.editor'
                            ]
                        }
                    }
                },
                {
                    category: 'permissions',
                    parent: null,
                    key: 'promotion',
                    roles: {
                        viewer: {
                            privileges: ['promotion:read'],
                            dependencies: []
                        },
                        editor: {
                            privileges: [
                                'promotion:update'
                            ],
                            dependencies: [
                                'promotion.viewer'
                            ]
                        },
                        creator: {
                            privileges: [
                                'promotion:create',
                                privilegesService.getPrivileges('rule.creator')
                            ],
                            dependencies: [
                                'promotion.viewer',
                                'promotion.editor'
                            ]
                        }
                    }
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'promotion.viewer',
                    'promotion:read',
                    'promotion.editor',
                    'promotion:update',
                    'promotion.creator',
                    'promotion:create',
                    'rule:create',
                    'rule:read',
                    'rule:update',
                    ...wrapper.vm.privileges.getRequiredPrivileges()
                ].sort()
            },
            contextMock
        );
    });

    it('should open the confirm password modal on save', async () => {
        const wrapper = createWrapper({
            aclPrivileges: ['users_and_permissions.editor']
        });
        await wrapper.setData({
            isLoading: false
        });

        let verifyUserModal = wrapper.find('sw-verify-user-modal-stub');
        expect(verifyUserModal.exists()).toBeFalsy();

        const saveButton = wrapper.find('.sw-users-permissions-role-detail__button-save');
        await saveButton.trigger('click.prevent');

        verifyUserModal = wrapper.find('sw-verify-user-modal-stub');
        expect(verifyUserModal.exists()).toBeTruthy();
    });

    it('should show the name of the role as the title', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            isLoading: false
        });

        const title = wrapper.find('h2');
        expect(title.text()).toBe('demoRole');
    });

    it('should not show the create new snippet when user deletes name', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            isLoading: false
        });

        const title = wrapper.find('h2');
        expect(title.text()).toBe('demoRole');

        wrapper.vm.role.name = '';
        await wrapper.vm.$nextTick();

        expect(title.text()).toBe('');
    });

    it('should show the create new role snippet as the title', async () => {
        const wrapper = createWrapper({}, {
            isNew: true
        });
        await wrapper.setData({
            isLoading: false
        });

        const title = wrapper.find('h2');
        expect(title.text()).toBe('sw-users-permissions.roles.general.labelCreateNewRole');
    });

    it('should replace the create new role snippet as the title when user types name', async () => {
        const wrapper = createWrapper({}, {
            isNew: true
        });
        await wrapper.setData({
            isLoading: false
        });

        const title = wrapper.find('h2');
        expect(title.text()).toBe('sw-users-permissions.roles.general.labelCreateNewRole');

        wrapper.vm.role.name = 'Test';
        await wrapper.vm.$nextTick();

        expect(title.text()).toBe('Test');
    });

    it('should disable the button and fields when no aclPrivileges exists', async () => {
        const wrapper = createWrapper({
            aclPrivileges: []
        });
        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-users-permissions-role-detail__button-save');
        expect(saveButton.attributes().disabled).toBe('disabled');
    });

    it('should enable the button and fields when edit aclPrivileges exists', async () => {
        const wrapper = createWrapper({
            aclPrivileges: ['users_and_permissions.editor']
        });
        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-users-permissions-role-detail__button-save');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });
});
