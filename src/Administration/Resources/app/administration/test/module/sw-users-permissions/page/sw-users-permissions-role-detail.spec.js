import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions-role-detail';
import PrivilegesService from 'src/app/service/privileges.service';

function createWrapper({
    privileges = [],
    privilegeMappingEntries = []
} = {}) {
    const privilegesService = new PrivilegesService();

    privilegeMappingEntries.forEach(mappingEntry => privilegesService.addPrivilegeMappingEntry(mappingEntry));

    return shallowMount(Shopware.Component.build('sw-users-permissions-role-detail'), {
        sync: false,
        stubs: {
            'sw-page': true
        },
        mocks: {
            $tc: t => t,
            $route: { params: { id: '12345789' } }
        },
        propsData: {},
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve({
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
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
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

        wrapper.vm.onSave();

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    ...wrapper.vm.requiredPrivileges
                ]
            },
            expect.anything()
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

        wrapper.vm.onSave();

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            { privileges: [
                'system.clear_cache',
                'system:clear:cache',
                'orders.create_discounts',
                'order:create:discount',
                ...wrapper.vm.requiredPrivileges
            ] },
            expect.anything()
        );
    });
});
