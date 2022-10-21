import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/components/sw-users-permissions-additional-permissions';

describe('module/sw-users-permissions/components/sw-users-permissions-additional-permissions', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions-additional-permissions'), {
            sync: false,
            stubs: {
                'sw-card': true,
                'sw-field': {
                    props: ['value'],
                    template: `
                        <input :value="value"
                               @click="$emit('change', !value)"
                               type="checkbox"
                               class="sw-field-stub">
                        </input>
                    `
                }
            },
            propsData: {
                role: {
                    privileges: []
                }
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
                        },
                        {
                            category: 'additional_permissions',
                            key: 'orders',
                            parent: null,
                            roles: {
                                create_discounts: {
                                    dependencies: [],
                                    privileges: ['order:create:discount']
                                }
                            }
                        },
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
                            category: 'additional_permissions',
                            key: 'app',
                            parent: null,
                            roles: {
                                all: {
                                    dependencies: ['app.appExample'],
                                    privileges: []
                                },
                                appExample: {
                                    dependencies: [],
                                    privileges: []
                                }
                            }
                        }
                    ]
                },
                appAclService: {
                    addAppPermissions: () => {}
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
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
            '.sw-users-permissions-additional-permissions_system + .sw-users-permissions-additional-permissions__switches'
        );
        const systemFields = systemRoles.findAll('.sw-field-stub');

        expect(systemFields.length).toBe(3);
        expect(systemFields.at(0).attributes().label).toBe('sw-privileges.additional_permissions.system.clear_cache');
        expect(systemFields.at(1).attributes().label).toBe('sw-privileges.additional_permissions.system.core_update');
        expect(systemFields.at(2).attributes().label).toBe('sw-privileges.additional_permissions.system.plugin_maintain');

        const ordersRoles = wrapper.find(
            '.sw-users-permissions-additional-permissions_orders + .sw-users-permissions-additional-permissions__switches'
        );
        const ordersFields = ordersRoles.findAll('.sw-field-stub');

        expect(ordersFields.length).toBe(1);
        expect(ordersFields.at(0).attributes().label).toBe('sw-privileges.additional_permissions.orders.create_discounts');
    });

    it('should contain the a true value in a field when the privilege is in roles', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache']
            }
        });

        const clearCacheField = wrapper.find(
            '.sw-field-stub[label="sw-privileges.additional_permissions.system.clear_cache"]'
        );

        expect(clearCacheField.props().value).toBeTruthy();
    });

    it('should contain the a false value in a field when the privilege is not in roles', async () => {
        const clearCacheField = wrapper.find(
            '.sw-field-stub[label="sw-privileges.additional_permissions.system.clear_cache"]'
        );

        expect(clearCacheField.props().value).toBeFalsy();
    });

    it('should add the checked value to the role privileges', async () => {
        const clearCacheField = wrapper.find(
            '.sw-field-stub[label="sw-privileges.additional_permissions.system.clear_cache"]'
        );

        expect(clearCacheField.props().value).toBeFalsy();

        await clearCacheField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(clearCacheField.props().value).toBeTruthy();
    });

    it('should remove the value when it get unchecked', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache']
            }
        });

        const clearCacheField = wrapper.find(
            '.sw-field-stub[label="sw-privileges.additional_permissions.system.clear_cache"]'
        );

        expect(clearCacheField.props().value).toBeTruthy();

        await clearCacheField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).not.toContain('system.clear_cache');
        expect(clearCacheField.props().value).toBeFalsy();
    });

    it('should disable all checkboxes', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache']
            },
            disabled: true
        });

        wrapper.findAll('.sw-field-stub').wrappers.forEach(field => {
            expect(field.attributes().disabled).toBe('disabled');
        });
    });

    it('should add the checked value to all app privileges when the all option checked', async () => {
        const allField = wrapper.find('.sw-field-stub[label="sw-privileges.additional_permissions.app.all"]');

        expect(allField.props().value).toBeFalsy();

        await allField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).toContain('app.all');
        expect(wrapper.vm.role.privileges).toContain('app.appExample');
        expect(allField.props().value).toBeTruthy();
    });

    it('should unchecked all app privileges when the all option unchecked', async () => {
        const allField = wrapper.find('.sw-field-stub[label="sw-privileges.additional_permissions.app.all"]');

        await allField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).toContain('app.all');
        expect(wrapper.vm.role.privileges).toContain('app.appExample');

        await allField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).not.toContain('app.all');
        expect(wrapper.vm.role.privileges).not.toContain('app.appExample');
    });

    it('should disable all app privilege checkboxes when the all option checked', async () => {
        const allField = wrapper.find('.sw-field-stub[label="sw-privileges.additional_permissions.app.all"]');

        await allField.trigger('click');
        await wrapper.vm.$forceUpdate();

        const appExampleField = wrapper.find('.sw-field-stub[label="appExample"]');

        expect(appExampleField.attributes().disabled).toBe('disabled');
    });
});
