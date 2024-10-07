/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

describe('module/sw-users-permissions/components/sw-users-permissions-additional-permissions', () => {
    /**
     * @type VueWrapper
     */
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(
            await wrapTestComponent('sw-users-permissions-additional-permissions', {
                sync: true,
            }),
            {
                props: {
                    role: {
                        privileges: [],
                    },
                },
                attachTo: document.body,
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'sw-card': true,
                        'sw-switch-field': await wrapTestComponent('sw-switch-field', {
                            sync: true,
                        }),
                        'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', {
                            sync: true,
                        }),
                        'sw-base-field': true,
                        'sw-field-error': true,
                    },
                    provide: {
                        privileges: {
                            getPrivilegesMappings: () => [
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
                                            privileges: [
                                                'system:plugin:maintain',
                                            ],
                                        },
                                    },
                                },
                                {
                                    category: 'additional_permissions',
                                    key: 'orders',
                                    parent: null,
                                    roles: {
                                        create_discounts: {
                                            dependencies: [],
                                            privileges: [
                                                'order:create:discount',
                                            ],
                                        },
                                    },
                                },
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
                                    category: 'additional_permissions',
                                    key: 'app',
                                    parent: null,
                                    roles: {
                                        all: {
                                            dependencies: ['app.appExample'],
                                            privileges: [],
                                        },
                                        appExample: {
                                            dependencies: [],
                                            privileges: [],
                                        },
                                    },
                                },
                            ],
                        },
                        appAclService: {
                            addAppPermissions: () => {},
                        },
                    },
                },
            },
        );

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display all keys from the category additional_permissions', async () => {
        const systemPermissions = wrapper.find('.sw-users-permissions-additional-permissions_system');
        const ordersPermissions = wrapper.find('.sw-users-permissions-additional-permissions_orders');
        const appPermissions = wrapper.find('.sw-users-permissions-additional-permissions-app');

        expect(systemPermissions.exists()).toBeTruthy();
        expect(ordersPermissions.exists()).toBeTruthy();
        expect(appPermissions.exists()).toBeTruthy();
    });

    it('should not display keys from other categories', async () => {
        const productPermissions = wrapper.find('.sw-users-permissions-additional-permissions_product');

        expect(productPermissions.exists()).toBeFalsy();
    });

    it('should show all roles after the key', async () => {
        const systemRoles = wrapper.find(
            '.sw-users-permissions-additional-permissions_system + .sw-users-permissions-additional-permissions__switches',
        );
        const systemFields = systemRoles.findAllComponents({
            name: 'sw-switch-field-deprecated__wrapped',
        });

        expect(systemFields).toHaveLength(3);

        expect(systemFields[0].props().label).toBe('sw-privileges.additional_permissions.system.clear_cache');
        expect(systemFields[1].props().label).toBe('sw-privileges.additional_permissions.system.core_update');
        expect(systemFields[2].props().label).toBe('sw-privileges.additional_permissions.system.plugin_maintain');

        const ordersRoles = wrapper.find(
            '.sw-users-permissions-additional-permissions_orders + .sw-users-permissions-additional-permissions__switches',
        );
        const ordersFields = ordersRoles.findAllComponents({
            name: 'sw-switch-field-deprecated__wrapped',
        });

        expect(ordersFields).toHaveLength(1);
        expect(ordersFields[0].props().label).toBe('sw-privileges.additional_permissions.orders.create_discounts');
    });

    it('should contain the a true value in a field when the privilege is in roles', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
        });

        await flushPromises();

        const clearCacheField = wrapper.findAllComponents({ name: 'sw-switch-field-deprecated__wrapped' }).find((field) => {
            return field.classes().includes('sw_users_permissions_additional_permissions_system_clear_cache');
        });
        expect(clearCacheField.props().value).toBeTruthy();
    });

    it('should contain the a false value in a field when the privilege is not in roles', async () => {
        const clearCacheField = wrapper.findComponent('.sw_users_permissions_additional_permissions_system_clear_cache');

        expect(clearCacheField.props().value).toBeFalsy();
    });

    it('should add the checked value to the role privileges', async () => {
        const clearCacheField = wrapper.findAllComponents({ name: 'sw-switch-field-deprecated__wrapped' }).find((field) => {
            return field.classes().includes('sw_users_permissions_additional_permissions_system_clear_cache');
        });

        expect(clearCacheField.props().value).toBeFalsy();

        await clearCacheField.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(clearCacheField.props().value).toBeTruthy();
    });

    it('should remove the value when it get unchecked', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
        });

        const clearCacheField = wrapper.findAllComponents({ name: 'sw-switch-field-deprecated__wrapped' }).find((field) => {
            return field.classes().includes('sw_users_permissions_additional_permissions_system_clear_cache');
        });

        expect(clearCacheField.props().value).toBeTruthy();

        await clearCacheField.find('input').trigger('click');
        await flushPromises();
        await clearCacheField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).not.toContain('system.clear_cache');
        expect(clearCacheField.props().value).toBeFalsy();
    });

    it('should disable all checkboxes', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
            disabled: true,
        });
        await flushPromises();

        wrapper.findAll('.sw-field--switch').forEach((field) => {
            expect(field.classes()).toContain('is--disabled');
        });
    });

    it('should add the checked value to all app privileges when the all option checked', async () => {
        const allField = wrapper.findAllComponents({ name: 'sw-switch-field-deprecated__wrapped' }).find((field) => {
            return field.classes().includes('sw_users_permissions_additional_permissions_app_all');
        });

        expect(allField.props().value).toBeFalsy();

        await allField.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('app.all');
        expect(wrapper.vm.role.privileges).toContain('app.appExample');
        expect(allField.props().value).toBeTruthy();
    });

    it('should unchecked all app privileges when the all option unchecked', async () => {
        const allField = wrapper.findComponent('.sw_users_permissions_additional_permissions_app_all');

        await allField.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('app.all');
        expect(wrapper.vm.role.privileges).toContain('app.appExample');

        await allField.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.vm.role.privileges).not.toContain('app.all');
        expect(wrapper.vm.role.privileges).not.toContain('app.appExample');
    });

    it('should disable all app privilege checkboxes when the all option checked', async () => {
        const allField = wrapper.findComponent('.sw_users_permissions_additional_permissions_app_all');

        await allField.find('input').trigger('click');
        await flushPromises();

        const appExampleField = wrapper.find('.sw_users_permissions_additional_permissions_app_appExample');

        expect(appExampleField.classes()).toContain('is--disabled');
    });
});
